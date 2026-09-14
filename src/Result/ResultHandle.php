<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Result;

use Brahmic\ApiSutra\Continuation\ContinuationAwaitOptions;
use Brahmic\ApiSutra\Continuation\ContinuationOutcome;
use Brahmic\ApiSutra\Continuation\ContinuationService;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Exceptions\Configuration\ContinuationConfigurationException;
use Brahmic\ApiSutra\Request\RequestSpecResolver;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;

/**
 * Унифицированная обёртка результата выполнения запроса.
 *
 * Нюансы:
 * - Ленивая материализация: Promise разрешается только при первом обращении к raw()/resolved().
 * - await()/awaitAs() кешируют итог в рамках одного handle и не запускают повторный polling.
 * - awaitAs() гидратирует сохранённый Ready-payload при смене DTO-типа.
 * - Continuation orchestration доступен только если handle создан с client/sourceRequest контекстом.
 *
 * @see docs/guides/provider-async-await.md
 * @see docs/guides/errors.md
 */
final class ResultHandle
{
    private ExecutionResult|PromiseInterface $result;
    private ?ContinuationOutcome $awaitOutcome = null;
    private ?string $awaitType = null;

    public function __construct(
        ExecutionResult|PromiseInterface $result,
        private readonly ResolvedResultFactoryInterface $factory,
        private readonly ?ClientInterface $client = null,
        private readonly ?RequestInterface $sourceRequest = null,
    ) {
        $this->result = $result;
    }

    public function raw(): ExecutionResult
    {
        if ($this->result instanceof PromiseInterface) {
            $resolved = $this->result->wait();
            if (!$resolved instanceof ExecutionResult) {
                throw new ConfigurationException('Некорректный тип результата промиса');
            }
            $this->result = $resolved;
            return $resolved;
        }

        return $this->result;
    }

    public function resolved(): ResolvedResultInterface
    {
        return $this->factory->make($this->raw());
    }

    public function resolvedAsync(): PromiseInterface
    {
        if ($this->result instanceof PromiseInterface) {
            return $this->result->then(
                fn (ExecutionResult $result) => $this->factory->make($result),
            );
        }

        $promise = new Promise();
        $promise->resolve($this->factory->make($this->result));
        return $promise;
    }

    /**
     * Вернуть промис результата (внутренний API для конкурентного выполнения).
     */
    public function rawAsync(): PromiseInterface
    {
        if ($this->result instanceof PromiseInterface) {
            return $this->result;
        }

        $promise = new Promise();
        $promise->resolve($this->result);
        return $promise;
    }

    public function dataOrFail(): mixed
    {
        $result = $this->raw()->throw();
        return $result->data;
    }

    public function continuationToken(): ?string
    {
        return $this->resolved()->continuationToken();
    }

    public function continuationTokenOrFail(): string
    {
        return $this->resolved()->continuationTokenOrFail();
    }

    public function await(?ContinuationAwaitOptions $options = null): mixed
    {
        if ($this->awaitOutcome !== null) {
            return $this->awaitOutcome->value;
        }

        $this->awaitOutcome = $this->resolveContinuation($options);

        return $this->awaitOutcome->value;
    }

    /**
     * Дождаться финального результата и вернуть его в указанном DTO-типе.
     *
     * Если await уже выполнялся, метод не запускает polling повторно:
     * используется сохранённый payload до гидрации.
     */
    public function awaitAs(string $finalType, ?ContinuationAwaitOptions $options = null): mixed
    {
        $resolvedType = trim($finalType);
        if ($resolvedType === '') {
            throw new ContinuationConfigurationException('finalType не должен быть пустым');
        }

        if ($this->awaitOutcome !== null && $this->awaitType === $resolvedType) {
            return $this->awaitOutcome->value;
        }

        if ($this->awaitOutcome !== null) {
            $mapped = $this->continuationService()->hydrateOutcome($this->awaitOutcome, $resolvedType);
            $this->awaitType = $resolvedType;
            $this->awaitOutcome = $mapped;

            return $mapped->value;
        }

        $this->awaitOutcome = $this->resolveContinuation($options, $resolvedType);

        return $this->awaitOutcome->value;
    }

    /**
     * Sugar для быстрого доступа к debug-снимку prepared request.
     *
     * @return array{
     *   method: string,
     *   url: string,
     *   headers: array<string, string>,
     *   bodyRaw: ?string,
     *   hasStream: bool
     * }|null
     */
    public function requestDebug(bool $redactSensitive = true): ?array
    {
        return $this->raw()->requestDebug($redactSensitive);
    }

    public function requestDebugJson(
        bool $redactSensitive = true,
        int $flags = JSON_UNESCAPED_UNICODE,
    ): ?string {
        return $this->raw()->requestDebugJson($redactSensitive, $flags);
    }

    private function continuationService(): ContinuationService
    {
        if ($this->client === null) {
            throw new ContinuationConfigurationException(
                'Continuation orchestration недоступен без client контекста ResultHandle',
            );
        }

        return $this->client->continuation();
    }

    private function resolveContinuation(
        ?ContinuationAwaitOptions $options,
        ?string $finalType = null,
    ): ContinuationOutcome {
        $outcome = $this->continuationService()->resolveFromStartResult(
            startResult: $this->raw(),
            sourceRequest: $this->sourceRequest,
            finalTypeOverride: $finalType,
            options: $options,
        );
        $request = $this->sourceRequest instanceof RequestExecutionInterface
            ? $this->sourceRequest->getRequest()
            : $this->sourceRequest;
        $declaredType = $request === null
            ? null
            : (new RequestSpecResolver())->resolveClass($request::class)->continuationResult?->finalType;
        $this->awaitType = $finalType ?? $declaredType;

        return $outcome;
    }
}
