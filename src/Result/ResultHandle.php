<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Result;

use Brahmic\ApiSutra\Continuation\ContinuationAwaitOptions;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Exceptions\Configuration\ContinuationConfigurationException;
use Brahmic\ApiSutra\Serialization\Hydrator;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Throwable;

/**
 * Унифицированная обёртка результата выполнения запроса.
 *
 * Нюансы:
 * - Ленивая материализация: Promise разрешается только при первом обращении к raw()/resolved().
 * - await()/awaitAs() кешируют итог в рамках одного handle и не запускают повторный polling.
 * - awaitAs() умеет доприводить уже закешированный await-результат к новому DTO-типу.
 * - Continuation orchestration доступен только если handle создан с client/sourceRequest контекстом.
 *
 * @see docs/guides/provider-async-await.md
 * @see docs/guides/errors.md
 */
final class ResultHandle
{
    private ExecutionResult|PromiseInterface $result;
    private bool $awaitResolved = false;
    private mixed $awaitValue = null;
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
        if ($this->awaitResolved) {
            return $this->awaitValue;
        }

        $value = $this->resolveContinuation($options);
        $this->awaitResolved = true;
        $this->awaitType = null;
        $this->awaitValue = $value;

        return $value;
    }

    /**
     * Дождаться финального результата и вернуть его в указанном DTO-типе.
     *
     * Если await уже выполнялся, метод не запускает polling повторно:
     * используется cached значение с попыткой гидрации к новому типу.
     */
    public function awaitAs(string $finalType, ?ContinuationAwaitOptions $options = null): mixed
    {
        $resolvedType = trim($finalType);
        if ($resolvedType === '') {
            throw new ContinuationConfigurationException('finalType не должен быть пустым');
        }

        if ($this->awaitResolved && $this->awaitType === $resolvedType) {
            return $this->awaitValue;
        }

        if ($this->awaitResolved) {
            $mapped = $this->mapCachedAwaitValue($resolvedType);
            $this->awaitType = $resolvedType;
            $this->awaitValue = $mapped;

            return $mapped;
        }

        $value = $this->resolveContinuation($options, $resolvedType);
        $this->awaitResolved = true;
        $this->awaitType = $resolvedType;
        $this->awaitValue = $value;

        return $value;
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

    private function resolveContinuation(?ContinuationAwaitOptions $options, ?string $finalType = null): mixed
    {
        if ($this->client === null) {
            throw new ContinuationConfigurationException(
                'Continuation orchestration недоступен без client контекста ResultHandle',
            );
        }

        return $this->client->continuation()->awaitFromStartResult(
            startResult: $this->raw(),
            sourceRequest: $this->sourceRequest,
            finalTypeOverride: $finalType,
            options: $options,
        );
    }

    private function mapCachedAwaitValue(string $finalType): mixed
    {
        $value = $this->awaitValue;
        if (is_object($value) && $value instanceof $finalType) {
            return $value;
        }

        if (!is_array($value) && !is_object($value)) {
            throw new ContinuationConfigurationException(
                'Не удалось привести cached await-результат к типу ' . $finalType,
            );
        }

        try {
            return Hydrator::default()->hydrate($value, $finalType);
        } catch (Throwable $exception) {
            throw new ContinuationConfigurationException(
                'Не удалось привести cached await-результат к типу ' . $finalType . ': ' . $exception->getMessage(),
            );
        }
    }
}
