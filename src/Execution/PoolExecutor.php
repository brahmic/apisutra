<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Execution;

use Brahmic\ApiSutra\Collections\ErrorCollection;
use Brahmic\ApiSutra\Collections\RequestCollection;
use Brahmic\ApiSutra\Collections\ResultCollection;
use Brahmic\ApiSutra\Config\PoolConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Concurrency\ConcurrencyResolverInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Execution\RequestRole;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Request\RequestExecution;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\Result\PoolResult;
use Closure;
use GuzzleHttp\Promise\Each;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Throwable;

final class PoolExecutor
{
    private ?Closure $onResponse = null;
    private ?Closure $onException = null;
    private readonly int|Closure|ConcurrencyResolverInterface $concurrency;
    private readonly ExecutionErrorFactory $errorFactory;
    private RequestRole $role = RequestRole::Nested;

    public function __construct(
        private readonly ClientInterface $client,
        private readonly iterable $requests,
        int|callable|ConcurrencyResolverInterface $concurrency = 5,
        private readonly ?PoolConfig $config = null,
    ) {
        $this->concurrency = $this->normalizeConcurrency($concurrency);
        $this->errorFactory = new ExecutionErrorFactory();
    }

    public function withResponseHandler(callable $handler): self
    {
        return $this->cloneWith(static function (self $clone) use ($handler): void {
            $clone->onResponse = Closure::fromCallable($handler);
        });
    }

    public function withExceptionHandler(callable $handler): self
    {
        return $this->cloneWith(static function (self $clone) use ($handler): void {
            $clone->onException = Closure::fromCallable($handler);
        });
    }

    public function withRole(RequestRole $role): self
    {
        return $this->cloneWith(static function (self $clone) use ($role): void {
            $clone->role = $role;
        });
    }

    public function send(): PoolResult
    {
        $requests = $this->normalizeRequests()->all();
        $results = $this->executePool($requests);

        return $this->buildPoolResult($results);
    }

    public function sendAsync(): PromiseInterface
    {
        $promise = new Promise();

        try {
            $promise->resolve($this->send());
        } catch (Throwable $exception) {
            $promise->reject($exception);
        }

        return $promise;
    }

    private function normalizeRequests(): RequestCollection
    {
        $result = [];
        foreach ($this->requests as $index => $request) {
            $resolved = $this->resolveRequestItem($request, $index);
            $result[] = $this->prepareRequest($resolved);
        }

        return RequestCollection::make($result);
    }

    /**
     * @param array<int, RequestInterface> $requests
     * @return array<int, ExecutionResult>
     */
    private function executePool(array $requests): array
    {
        $results = [];
        $shouldStop = false;
        $stopOnFailure = $this->shouldStopOnFailure();
        $concurrency = $this->resolveConcurrency(count($requests), 0);
        $generator = $this->buildGenerator($requests, $shouldStop);

        Each::ofLimit(
            $generator(),
            $concurrency,
            $this->buildResponseHandler($results, $requests, $stopOnFailure, $shouldStop),
            $this->buildExceptionHandler($results, $requests, $stopOnFailure, $shouldStop),
        )->wait();

        return $this->normalizeResults($results);
    }

    private function buildPoolResult(array $results): PoolResult
    {
        $collection = ResultCollection::make($results);
        $status = $collection->summarize()->status;

        return new PoolResult(
            data: null,
            status: $status,
            errors: new ErrorCollection([]),
            nested: $collection->all(),
        );
    }

    /**
     * @param array<int, RequestInterface> $requests
     */
    private function buildGenerator(array $requests, bool &$shouldStop): Closure
    {
        return function () use ($requests, &$shouldStop) {
            foreach ($requests as $index => $request) {
                if ($shouldStop) {
                    break;
                }
                yield $index => $this->client->sendAsync($request)->rawAsync();
            }
        };
    }

    /**
     * @param array<int, ExecutionResult> $results
     * @param array<int, RequestInterface> $requests
     */
    private function buildResponseHandler(
        array &$results,
        array $requests,
        bool $stopOnFailure,
        bool &$shouldStop,
    ): Closure {
        return function (ExecutionResult $result, int $index) use (&$results, &$shouldStop, $stopOnFailure, $requests): void {
            $results[$index] = $result;
            if ($this->onResponse) {
                ($this->onResponse)($result, $requests[$index]);
            }
            if ($stopOnFailure && $result->isFailed()) {
                $shouldStop = true;
            }
        };
    }

    /**
     * @param array<int, ExecutionResult> $results
     * @param array<int, RequestInterface> $requests
     */
    private function buildExceptionHandler(
        array &$results,
        array $requests,
        bool $stopOnFailure,
        bool &$shouldStop,
    ): Closure {
        return function (mixed $reason, int $index) use (&$results, &$shouldStop, $stopOnFailure, $requests): void {
            $exception = $reason instanceof Throwable
                ? $reason
                : new \RuntimeException('Ошибка выполнения pool запроса');
            $request = $requests[$index] ?? null;
            if ($request instanceof RequestInterface) {
                $result = $this->errorFactory->buildExceptionResult($request, $exception);
                $results[$index] = $result;
                if ($this->onException) {
                    ($this->onException)($exception, $request);
                }
            }
            if ($stopOnFailure) {
                $shouldStop = true;
            }
        };
    }

    /**
     * @param array<int, ExecutionResult> $results
     * @return array<int, ExecutionResult>
     */
    private function normalizeResults(array $results): array
    {
        ksort($results);
        return array_values($results);
    }

    private function shouldStopOnFailure(): bool
    {
        return $this->config->stopOnFailure ?? false;
    }

    private function resolveRequestItem(mixed $request, int $index): RequestInterface
    {
        if (!$request instanceof RequestInterface) {
            throw new ConfigurationException(
                $this->errorFactory->unsupportedItemMessage('pool', $index, $request),
            );
        }

        return $request;
    }

    private function prepareRequest(RequestInterface $request): RequestInterface
    {
        if ($request instanceof RequestExecutionInterface) {
            $inner = $request->getRequest();
            if ($inner instanceof AbstractRequest) {
                $inner->setClient($this->client);
            }

            return new RequestExecution(
                request: $inner,
                options: $request->getOptions()->withRole($this->role),
                paginationOptions: $request->getPaginationOptions(),
            );
        }

        if ($request instanceof AbstractRequest) {
            $request->setClient($this->client);
            $request = $request->withRole($this->role);
        }

        return $request;
    }

    private function resolveConcurrency(int $pending, int $completed): int
    {
        return match (true) {
            is_int($this->concurrency) => max(1, $this->concurrency),
            $this->concurrency instanceof Closure => max(1, (int) ($this->concurrency)($pending, $completed)),
            $this->concurrency instanceof ConcurrencyResolverInterface => max(1, $this->concurrency->getConcurrency($pending, $completed)),
            default => 5,
        };
    }

    private function normalizeConcurrency(int|callable|ConcurrencyResolverInterface $concurrency): int|Closure|ConcurrencyResolverInterface
    {
        if ($concurrency instanceof ConcurrencyResolverInterface) {
            return $concurrency;
        }

        if (is_int($concurrency)) {
            return $concurrency;
        }

        return Closure::fromCallable($concurrency);
    }

    private function cloneWith(callable $mutate): self
    {
        $clone = clone $this;
        $mutate($clone);
        return $clone;
    }
}
