<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Continuation;

use Brahmic\ApiSutra\Attributes\Response\ContinuationResult;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestOptionsProviderInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Continuation\ContinuationMode;
use Brahmic\ApiSutra\Exceptions\Configuration\ContinuationConfigurationException;
use Brahmic\ApiSutra\Request\RequestSpecResolver;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Support\ArrayPath;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Throwable;

/**
 * Сервис orchestration для unified provider async-await сценариев.
 *
 * Инварианты:
 * - Не хардкодит provider-протокол: token извлекается только через configured extractor.
 * - Приоритет режима: runtime override -> ContinuationResult.defaultMode -> ClientConfig.defaultContinuationMode.
 * - В polling-цикле failed-ответ не считается фатальным, пока есть continuation token.
 * - Ошибка пробрасывается только когда продолжение невозможно (нет финала и нет token).
 * - Контракт poll-request валидируется fail-fast: ровно один обязательный scalar-параметр.
 *
 * @see docs/guides/provider-async-await.md
 * @see docs/guides/continuation-token.md
 */
final readonly class ContinuationService
{
    public function __construct(
        private ClientInterface $client,
    ) {}

    public function awaitFromStartResult(
        ExecutionResult $startResult,
        ?RequestInterface $sourceRequest = null,
        ?string $finalTypeOverride = null,
        ?ContinuationAwaitOptions $options = null,
    ): mixed {
        $sourceClass = $this->resolveSourceRequestClass($sourceRequest);
        $continuation = $this->resolveContinuationResult($sourceClass);
        $mode = $this->resolveMode($sourceRequest, $continuation);
        $finalType = $finalTypeOverride ?? $continuation?->finalType;
        $unwrap = $continuation?->unwrap;
        $pollRequestClass = $continuation?->pollRequest ?? $this->client->getConfig()->defaultPollRequest;
        $options ??= new ContinuationAwaitOptions();

        if ($mode === ContinuationMode::Sync) {
            $resolved = $this->tryResolveFinal(
                $this->resolveContinuationPayload($startResult),
                $finalType,
                $unwrap,
            );
            if ($resolved['resolved'] === true) {
                return $resolved['value'];
            }

            if ($startResult->isFailed()) {
                $startResult->throw();
            }

            throw new ContinuationConfigurationException(
                'Не удалось собрать финальный результат в режиме Sync из стартового ответа',
            );
        }

        if ($mode === ContinuationMode::Auto) {
            $resolved = $this->tryResolveFinal(
                $this->resolveContinuationPayload($startResult),
                $finalType,
                $unwrap,
            );
            if ($resolved['resolved'] === true) {
                return $resolved['value'];
            }
        }

        $token = $this->extractToken($startResult);
        if (!is_string($token) || $token === '') {
            if ($startResult->isFailed()) {
                $startResult->throw();
            }

            throw new ContinuationConfigurationException(
                'Continuation token отсутствует в стартовом результате',
            );
        }

        return $this->awaitByTokenInternal(
            token: $token,
            finalType: $finalType,
            unwrap: $unwrap,
            pollRequestClass: $pollRequestClass,
            options: $options,
        );
    }

    public function awaitByToken(
        string $token,
        string $sourceRequestClass,
        ?ContinuationAwaitOptions $options = null,
    ): mixed {
        $sourceClass = trim($sourceRequestClass);
        if ($sourceClass === '') {
            throw new ContinuationConfigurationException('sourceRequestClass не должен быть пустым');
        }

        $continuation = $this->resolveContinuationResult($sourceClass);
        if (!$continuation instanceof ContinuationResult) {
            throw new ContinuationConfigurationException(
                'Для sourceRequestClass не задан атрибут ContinuationResult: ' . $sourceClass,
            );
        }

        $pollRequestClass = $continuation->pollRequest ?? $this->client->getConfig()->defaultPollRequest;

        return $this->awaitByTokenInternal(
            token: $token,
            finalType: $continuation->finalType,
            unwrap: $continuation->unwrap,
            pollRequestClass: $pollRequestClass,
            options: $options ?? new ContinuationAwaitOptions(),
        );
    }

    public function awaitByTokenAs(
        string $token,
        string $finalType,
        ?ContinuationAwaitOptions $options = null,
    ): mixed {
        return $this->awaitByTokenInternal(
            token: $token,
            finalType: $finalType,
            unwrap: null,
            pollRequestClass: $this->client->getConfig()->defaultPollRequest,
            options: $options ?? new ContinuationAwaitOptions(),
        );
    }

    private function awaitByTokenInternal(
        string $token,
        ?string $finalType,
        ?string $unwrap,
        ?string $pollRequestClass,
        ContinuationAwaitOptions $options,
    ): mixed {
        if ($pollRequestClass === null || trim($pollRequestClass) === '') {
            throw new ContinuationConfigurationException(
                'Не задан poll request: укажите ContinuationResult.pollRequest или ClientConfig.defaultPollRequest',
            );
        }

        $currentToken = trim($token);
        if ($currentToken === '') {
            throw new ContinuationConfigurationException('Continuation token не должен быть пустым');
        }

        for ($attempt = 1; $attempt <= $options->maxAttempts; $attempt++) {
            $pollResult = $this->sendPollRequest($pollRequestClass, $currentToken);
            $resolved = $this->tryResolveFinal(
                $this->resolveContinuationPayload($pollResult),
                $finalType,
                $unwrap,
            );
            if ($resolved['resolved'] === true) {
                return $resolved['value'];
            }

            $nextToken = $this->extractToken($pollResult);
            if (is_string($nextToken) && trim($nextToken) !== '') {
                $currentToken = trim($nextToken);

                if ($attempt < $options->maxAttempts && $options->intervalMs > 0) {
                    usleep($options->intervalMs * 1000);
                }

                continue;
            }

            if ($pollResult->isFailed()) {
                $pollResult->throw();
            }

            throw new ContinuationConfigurationException(
                'Polling не вернул финальные данные и continuation token для следующей попытки',
            );
        }

        throw new ContinuationConfigurationException(
            'Превышен лимит polling попыток: ' . $options->maxAttempts,
        );
    }

    /**
     * Возвращает payload для continuation-логики с fallback-цепочкой.
     *
     * Порядок:
     * - hydrated data из ExecutionResult;
     * - debug response payload (если debug включён);
     * - payload из ошибки первого failed-ответа.
     */
    private function resolveContinuationPayload(ExecutionResult $result): mixed
    {
        if ($result->data !== null) {
            return $result->data;
        }

        $debugPayload = $result->debug?->response?->json();
        if (is_array($debugPayload)) {
            return $debugPayload;
        }

        $errorPayload = $result->errors->first()?->response?->json();
        if (is_array($errorPayload)) {
            return $errorPayload;
        }

        return $result->data;
    }

    private function sendPollRequest(string $pollRequestClass, string $token): ExecutionResult
    {
        $request = $this->instantiatePollRequest($pollRequestClass, $token);
        if ($request instanceof AbstractRequest) {
            $request->setClient($this->client);
        }

        return $this->client->send($request)->raw();
    }

    private function instantiatePollRequest(string $pollRequestClass, string $token): RequestInterface
    {
        $class = trim($pollRequestClass);
        if ($class === '') {
            throw new ContinuationConfigurationException('pollRequestClass не должен быть пустым');
        }

        if (!class_exists($class)) {
            throw new ContinuationConfigurationException('Класс poll request не найден: ' . $class);
        }

        if (!is_subclass_of($class, RequestInterface::class)) {
            throw new ContinuationConfigurationException(
                'Класс poll request должен реализовывать RequestInterface: ' . $class,
            );
        }

        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            throw new ContinuationConfigurationException(
                'Poll request должен иметь конструктор с обязательным token-параметром: ' . $class,
            );
        }

        $required = array_values(array_filter(
            $constructor->getParameters(),
            static fn (ReflectionParameter $parameter): bool => !$parameter->isOptional(),
        ));

        if (count($required) !== 1) {
            throw new ContinuationConfigurationException(
                'Poll request должен иметь ровно один обязательный параметр конструктора: ' . $class,
            );
        }

        $tokenParameter = $required[0];
        $tokenValue = $this->castTokenForParameter($token, $tokenParameter);

        $args = [];
        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->getName() === $tokenParameter->getName()) {
                $args[] = $tokenValue;
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $args[] = $parameter->getDefaultValue();
                continue;
            }

            throw new ContinuationConfigurationException(
                'Не удалось подготовить аргументы конструктора poll request: ' . $class,
            );
        }

        $instance = $reflection->newInstanceArgs($args);
        if (!$instance instanceof RequestInterface) {
            throw new ContinuationConfigurationException(
                'Класс poll request должен реализовывать RequestInterface: ' . $class,
            );
        }

        return $instance;
    }

    private function castTokenForParameter(string $token, ReflectionParameter $parameter): string|int|float|bool
    {
        $type = $parameter->getType();
        if (!$type instanceof ReflectionNamedType || !$type->isBuiltin()) {
            throw new ContinuationConfigurationException(
                'Тип обязательного token-параметра должен быть scalar: ' . $parameter->getName(),
            );
        }

        return match ($type->getName()) {
            'string' => $token,
            'int' => $this->castTokenToInt($token, $parameter->getName()),
            'float' => $this->castTokenToFloat($token, $parameter->getName()),
            'bool' => $this->castTokenToBool($token, $parameter->getName()),
            default => throw new ContinuationConfigurationException(
                'Тип обязательного token-параметра должен быть scalar: ' . $parameter->getName(),
            ),
        };
    }

    private function castTokenToInt(string $token, string $parameter): int
    {
        if (preg_match('/^-?\d+$/', $token) !== 1) {
            throw new ContinuationConfigurationException(
                'Не удалось привести token к int для параметра: ' . $parameter,
            );
        }

        return (int) $token;
    }

    private function castTokenToFloat(string $token, string $parameter): float
    {
        if (!is_numeric($token)) {
            throw new ContinuationConfigurationException(
                'Не удалось привести token к float для параметра: ' . $parameter,
            );
        }

        return (float) $token;
    }

    private function castTokenToBool(string $token, string $parameter): bool
    {
        $normalized = strtolower(trim($token));
        return match ($normalized) {
            '1', 'true', 'yes' => true,
            '0', 'false', 'no' => false,
            default => throw new ContinuationConfigurationException(
                'Не удалось привести token к bool для параметра: ' . $parameter,
            ),
        };
    }

    /**
     * @return array{resolved: bool, value: mixed}
     */
    private function tryResolveFinal(mixed $data, ?string $finalType, ?string $unwrap): array
    {
        $payload = $this->applyUnwrap($data, $unwrap);

        if ($finalType === null || trim($finalType) === '') {
            if ($payload === null) {
                return ['resolved' => false, 'value' => null];
            }

            return ['resolved' => true, 'value' => $payload];
        }

        $resolvedType = trim($finalType);
        if (!class_exists($resolvedType)) {
            throw new ContinuationConfigurationException('Класс финального DTO не найден: ' . $resolvedType);
        }

        if (is_object($payload) && $payload instanceof $resolvedType) {
            return ['resolved' => true, 'value' => $payload];
        }

        if (!is_array($payload) && !is_object($payload)) {
            return ['resolved' => false, 'value' => null];
        }

        try {
            $dto = Hydrator::default()->hydrate($payload, $resolvedType);
        } catch (Throwable) {
            return ['resolved' => false, 'value' => null];
        }

        return ['resolved' => true, 'value' => $dto];
    }

    private function applyUnwrap(mixed $data, ?string $unwrap): mixed
    {
        if ($unwrap === null || trim($unwrap) === '') {
            return $data;
        }

        $unwrapped = ArrayPath::getByPath($data, $unwrap);

        return $unwrapped ?? $data;
    }

    private function extractToken(ExecutionResult $result): ?string
    {
        $extractor = $this->client->getConfig()->continuationTokenExtractor;
        if ($extractor === null) {
            return null;
        }

        return $extractor->extract($result);
    }

    private function resolveMode(?RequestInterface $sourceRequest, ?ContinuationResult $continuation): ContinuationMode
    {
        $modeOverride = $this->resolveContinuationModeOverride($sourceRequest);
        if ($modeOverride !== null) {
            return $modeOverride;
        }

        if ($continuation?->defaultMode !== null) {
            return $continuation->defaultMode;
        }

        return $this->client->getConfig()->defaultContinuationMode;
    }

    private function resolveContinuationModeOverride(?RequestInterface $sourceRequest): ?ContinuationMode
    {
        if ($sourceRequest instanceof RequestExecutionInterface) {
            return $sourceRequest->getOptions()->getContinuationModeOverride();
        }

        if ($sourceRequest instanceof RequestOptionsProviderInterface) {
            return $sourceRequest->getOptions()->getContinuationModeOverride();
        }

        return null;
    }

    private function resolveSourceRequestClass(?RequestInterface $sourceRequest): ?string
    {
        if ($sourceRequest instanceof RequestExecutionInterface) {
            return $sourceRequest->getRequest()::class;
        }

        if ($sourceRequest === null) {
            return null;
        }

        return $sourceRequest::class;
    }

    private function resolveContinuationResult(?string $sourceRequestClass): ?ContinuationResult
    {
        if ($sourceRequestClass === null || trim($sourceRequestClass) === '') {
            return null;
        }

        if (!class_exists($sourceRequestClass)) {
            throw new ContinuationConfigurationException('Класс source request не найден: ' . $sourceRequestClass);
        }

        $spec = (new RequestSpecResolver())->resolveClass($sourceRequestClass);

        return $spec->continuationResult;
    }
}
