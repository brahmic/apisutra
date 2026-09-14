<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Continuation;

use Brahmic\ApiSutra\Attributes\Response\ContinuationResult;
use Brahmic\ApiSutra\Contracts\Interfaces\Continuation\ContinuationStateResolverInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestOptionsProviderInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Continuation\ContinuationMode;
use Brahmic\ApiSutra\Enums\Continuation\ContinuationStatus;
use Brahmic\ApiSutra\Exceptions\Configuration\ContinuationConfigurationException;
use Brahmic\ApiSutra\Exceptions\Continuation\ContinuationAwaitException;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\Pipeline\Diagnostics\AuditLogger;
use Brahmic\ApiSutra\Request\RequestSpecResolver;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\Serialization\Hydrator;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Psr\Log\LogLevel;

/**
 * Исполняет ожидание по явному критерию протокола и гидратирует только Ready.
 * Ошибки гидратации и resolver никогда не превращаются в Pending.
 *
 * @see docs/guides/provider-async-await.md
 */
final readonly class ContinuationService
{
    public function __construct(
        private ClientInterface $client,
        private Hydrator $hydrator,
    ) {
    }

    public function awaitFromStartResult(
        ExecutionResult $startResult,
        ?RequestInterface $sourceRequest = null,
        ?string $finalTypeOverride = null,
        ?ContinuationAwaitOptions $options = null,
    ): mixed {
        return $this->resolveFromStartResult($startResult, $sourceRequest, $finalTypeOverride, $options)->value;
    }

    public function resolveFromStartResult(
        ExecutionResult $startResult,
        ?RequestInterface $sourceRequest = null,
        ?string $finalTypeOverride = null,
        ?ContinuationAwaitOptions $options = null,
    ): ContinuationOutcome {
        $sourceClass = $this->resolveSourceRequestClass($sourceRequest);
        $declaration = $this->resolveContinuationResult($sourceClass);
        $context = new ContinuationContext(
            finalType: $this->normalizeFinalType($finalTypeOverride ?? $declaration?->finalType),
            unwrap: $declaration?->unwrap,
            sourceRequestClass: $sourceClass,
            mode: $this->resolveMode($sourceRequest, $declaration),
        );
        $resolver = $this->resolveStateResolver($declaration, $context);
        $attempts = 0;
        if ($context->mode !== ContinuationMode::Async) {
            $attempts++;
            $outcome = $this->evaluate($startResult, $context, $resolver, $attempts);
            if ($outcome !== null) {
                return $outcome;
            }
            if ($context->mode === ContinuationMode::Sync) {
                throw $this->awaitError('final_not_ready', $startResult, $attempts);
            }
        }

        $token = $this->requireToken($startResult, $attempts);
        return $this->awaitByTokenInternal(
            $token,
            $context,
            $resolver,
            $declaration->pollRequest ?? $this->client->getConfig()->defaultPollRequest,
            $options ?? new ContinuationAwaitOptions(),
            $attempts,
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
        $declaration = $this->resolveContinuationResult($sourceClass);
        if ($declaration === null) {
            throw new ContinuationConfigurationException(
                'Для sourceRequestClass не задан атрибут ContinuationResult: ' . $sourceClass,
            );
        }
        $context = new ContinuationContext(
            $this->normalizeFinalType($declaration->finalType),
            $declaration->unwrap,
            $sourceClass,
            ContinuationMode::Async,
        );
        return $this->awaitByTokenInternal(
            $token,
            $context,
            $this->resolveStateResolver($declaration, $context),
            $declaration->pollRequest ?? $this->client->getConfig()->defaultPollRequest,
            $options ?? new ContinuationAwaitOptions(),
        )->value;
    }

    public function awaitByTokenAs(
        string $token,
        string $finalType,
        ?ContinuationAwaitOptions $options = null,
    ): mixed {
        $context = new ContinuationContext(
            $this->normalizeFinalType($finalType),
            null,
            null,
            ContinuationMode::Async,
        );
        return $this->awaitByTokenInternal(
            $token,
            $context,
            $this->resolveStateResolver(null, $context),
            $this->client->getConfig()->defaultPollRequest,
            $options ?? new ContinuationAwaitOptions(),
        )->value;
    }

    public function hydrateOutcome(ContinuationOutcome $outcome, string $finalType): ContinuationOutcome
    {
        $type = $this->normalizeFinalType($finalType);
        $shapeError = !is_array($outcome->payload) && !is_object($outcome->payload);
        try {
            if ($shapeError) {
                throw HydrationException::invalidValue(
                    'unexpected_response_shape',
                    $type,
                    get_debug_type($outcome->payload),
                );
            }
            $value = $this->hydrator->hydrate($outcome->payload, $type);
        } catch (HydrationException $exception) {
            $prefix = $outcome->path ?? ($shapeError ? '$' : '');
            if ($prefix !== '') {
                $exception = $exception->prependPath($prefix);
            }
            throw $this->awaitError(
                'final_hydration_failed',
                $outcome->lastResult,
                $outcome->attempts,
                $exception,
            );
        }

        return new ContinuationOutcome(
            $value,
            $outcome->payload,
            $outcome->path,
            $outcome->lastResult,
            $outcome->attempts,
        );
    }

    private function awaitByTokenInternal(
        string $token,
        ContinuationContext $context,
        ContinuationStateResolverInterface $resolver,
        ?string $pollRequestClass,
        ContinuationAwaitOptions $options,
        int $attempts = 0,
    ): ContinuationOutcome {
        if ($pollRequestClass === null || trim($pollRequestClass) === '') {
            throw new ContinuationConfigurationException(
                'Не задан poll request: укажите ContinuationResult.pollRequest или ClientConfig.defaultPollRequest',
            );
        }
        $currentToken = trim($token);
        if ($currentToken === '') {
            throw new ContinuationConfigurationException('Continuation token не должен быть пустым');
        }

        for ($poll = 1; $poll <= $options->maxAttempts; $poll++) {
            $result = $this->sendPollRequest($pollRequestClass, $currentToken);
            $attempts++;
            $outcome = $this->evaluate($result, $context, $resolver, $attempts);
            if ($outcome !== null) {
                return $outcome;
            }
            $currentToken = $this->requireToken($result, $attempts);
            if ($poll === $options->maxAttempts) {
                throw $this->awaitError('attempts_exhausted', $result, $attempts);
            }
            if ($options->intervalMs > 0) {
                usleep($options->intervalMs * 1000);
            }
        }

        // ContinuationAwaitOptions запрещает нулевой лимит.
        throw new ContinuationConfigurationException('Лимит polling должен быть положительным');
    }

    private function evaluate(
        ExecutionResult $result,
        ContinuationContext $context,
        ContinuationStateResolverInterface $resolver,
        int $attempts,
    ): ?ContinuationOutcome {
        $state = $resolver->resolve($result, $context);
        if ($state->status === ContinuationStatus::Pending) {
            return null;
        }
        if ($state->status === ContinuationStatus::Failed) {
            $result->throw();
            throw $this->awaitError('continuation_failed', $result, $attempts);
        }
        $outcome = new ContinuationOutcome($state->payload, $state->payload, $state->path, $result, $attempts);

        return $context->finalType === null ? $outcome : $this->hydrateOutcome($outcome, $context->finalType);
    }

    private function awaitError(
        string $reason,
        ExecutionResult $result,
        int $attempts,
        ?HydrationException $previous = null,
    ): ContinuationAwaitException {
        $message = match ($reason) {
            'final_hydration_failed' => 'Не удалось преобразовать готовый результат ожидания',
            'final_not_ready' => 'Финальный результат ещё не готов в режиме Sync',
            'continuation_token_missing' => 'Ожидание не получило token для продолжения',
            'attempts_exhausted' => 'Исчерпан лимит polling-запросов',
            default => 'Протокол завершил ожидание ошибкой',
        };
        $exception = new ContinuationAwaitException($message, $reason, $attempts, $result, $previous);
        (new AuditLogger($this->client->getConfig()))->log(LogLevel::ERROR, $message, $exception->context());
        return $exception;
    }

    private function requireToken(ExecutionResult $result, int $attempts): string
    {
        $extractor = $this->client->getConfig()->continuationTokenExtractor;
        if ($extractor === null) {
            throw new ContinuationConfigurationException(
                'Для продолжения ожидания требуется continuationTokenExtractor',
            );
        }
        $token = $extractor->extract($result);
        if ($token !== null && trim($token) !== '') {
            return trim($token);
        }
        $result->throw();
        throw $this->awaitError('continuation_token_missing', $result, $attempts);
    }

    private function resolveStateResolver(
        ?ContinuationResult $declaration,
        ContinuationContext $context,
    ): ContinuationStateResolverInterface {
        $class = $declaration?->stateResolver;
        if ($class !== null) {
            if (!is_subclass_of($class, ContinuationStateResolverInterface::class)) {
                throw new ContinuationConfigurationException('Неверный класс stateResolver: ' . $class);
            }
            $reflection = new ReflectionClass($class);
            $requiredParameters = $reflection->getConstructor()?->getNumberOfRequiredParameters() ?? 0;
            if (!$reflection->isInstantiable() || $requiredParameters > 0) {
                throw new ContinuationConfigurationException(
                    'stateResolver должен создаваться без аргументов: ' . $class,
                );
            }
            return new $class();
        }
        if ($context->unwrap !== null && trim($context->unwrap) !== '') {
            return new FinalPathStateResolver();
        }
        return $this->client->getConfig()->continuationStateResolver
            ?? throw new ContinuationConfigurationException(
                'Для ожидания требуется unwrap или continuationStateResolver',
            );
    }

    private function normalizeFinalType(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }
        $type = trim($type);
        if ($type === '' || !class_exists($type)) {
            throw new ContinuationConfigurationException('Класс финального DTO не найден: ' . $type);
        }
        return $type;
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
