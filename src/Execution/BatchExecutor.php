<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Execution;

use Brahmic\ApiSutra\Collections\ErrorCollection;
use Brahmic\ApiSutra\Collections\RequestCollection;
use Brahmic\ApiSutra\Collections\ResultCollection;
use Brahmic\ApiSutra\Config\BatchConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Execution\ExecutionMode;
use Brahmic\ApiSutra\Enums\Execution\FailStrategy;
use Brahmic\ApiSutra\Enums\Execution\RequestRole;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Execution\Batch\BatchContext;
use Brahmic\ApiSutra\Execution\Batch\BatchStrategyInterface;
use Brahmic\ApiSutra\Execution\Batch\ParallelBatchStrategy;
use Brahmic\ApiSutra\Execution\Batch\RequestResolverInterface;
use Brahmic\ApiSutra\Execution\Batch\Resolvers\CallableRequestResolver;
use Brahmic\ApiSutra\Execution\Batch\Resolvers\ClassStringRequestResolver;
use Brahmic\ApiSutra\Execution\Batch\Resolvers\RequestInstanceResolver;
use Brahmic\ApiSutra\Execution\Batch\SequentialBatchStrategy;
use Brahmic\ApiSutra\Request\RequestExecution;
use Brahmic\ApiSutra\Result\BatchResult;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\VO\Errors\RequestError;
use Brahmic\ApiSutra\VO\Metadata\BatchMeta;
use Brahmic\ApiSutra\VO\Metadata\ResultSummary;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Throwable;

final class BatchExecutor
{
    private ExecutionMode $mode;
    private FailStrategy $failStrategy;
    private int $concurrency;
    /**
     * @var array<int, RequestResolverInterface> Цепочка резолверов входных запросов.
     */
    private readonly array $requestResolvers;
    private readonly ExecutionErrorFactory $errorFactory;
    private readonly BatchStrategyInterface $sequentialStrategy;
    private readonly BatchStrategyInterface $parallelStrategy;

    public function __construct(
        private readonly ?ClientInterface $client,
        private readonly RequestCollection|array $requests,
        ExecutionMode $mode = ExecutionMode::Sequential,
        FailStrategy $failStrategy = FailStrategy::FailAll,
        int $concurrency = 5,
        private readonly ?PipelineContext $parent = null,
        private readonly RequestRole $role = RequestRole::Nested,
    ) {
        $this->mode = $mode;
        $this->failStrategy = $failStrategy;
        $this->concurrency = $concurrency;
        $this->requestResolvers = [
            new RequestInstanceResolver(),
            new CallableRequestResolver(),
            new ClassStringRequestResolver(),
        ];
        $this->errorFactory = new ExecutionErrorFactory();
        $this->sequentialStrategy = new SequentialBatchStrategy();
        $this->parallelStrategy = new ParallelBatchStrategy();
    }

    public static function fromConfig(
        ClientInterface $client,
        RequestCollection|array $requests,
        BatchConfig $config,
        ?PipelineContext $parent = null,
        RequestRole $role = RequestRole::Nested,
    ): self {
        return new self(
            client: $client,
            requests: $requests,
            mode: $config->mode,
            failStrategy: $config->failStrategy,
            concurrency: $config->concurrency,
            parent: $parent,
            role: $role,
        );
    }

    public function parallel(): static
    {
        return $this->withMode(ExecutionMode::Parallel);
    }

    public function sequential(): static
    {
        return $this->withMode(ExecutionMode::Sequential);
    }

    public function failStrategy(FailStrategy $strategy): static
    {
        return $this->withFailStrategy($strategy);
    }

    public function concurrency(int $value): static
    {
        return $this->withConcurrency($value);
    }

    public function withMode(ExecutionMode $mode): static
    {
        return $this->cloneWith(static function (self $clone) use ($mode): void {
            $clone->mode = $mode;
        });
    }

    public function withFailStrategy(FailStrategy $strategy): static
    {
        return $this->cloneWith(static function (self $clone) use ($strategy): void {
            $clone->failStrategy = $strategy;
        });
    }

    public function withConcurrency(int $value): static
    {
        return $this->cloneWith(static function (self $clone) use ($value): void {
            $clone->concurrency = $value;
        });
    }

    /**
     * @return array<int, ExecutionResult>
     */
    public function execute(): array
    {
        $context = $this->buildContext();
        $requests = $this->resolveRequests($context)->all();
        $this->assertClient($context);
        $strategy = $this->resolveStrategy($context->mode);

        return $this->executeWithStrategy($strategy, $context, $requests);
    }

    public function send(): BatchResult
    {
        $results = $this->execute();
        $collection = ResultCollection::make($results);
        return $this->buildBatchResult($collection);
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

    private function cloneWith(callable $mutate): static
    {
        $clone = clone $this;
        $mutate($clone);
        return $clone;
    }

    private function resolveRequests(BatchContext $context): RequestCollection
    {
        $collection = $this->normalizeRequests();
        $resolved = [];
        foreach ($collection->all() as $index => $item) {
            $resolvedRequest = $this->resolveRequestItem($item, $context, $index);
            $resolved[] = $this->prepareRequest($resolvedRequest, $context);
        }

        return RequestCollection::make($resolved);
    }

    private function prepareRequest(RequestInterface $request, BatchContext $context): RequestInterface
    {
        if ($request instanceof RequestExecutionInterface) {
            $inner = $request->getRequest();
            if ($inner instanceof AbstractRequest && $context->client !== null) {
                $inner->setClient($context->client);
            }

            return new RequestExecution(
                request: $inner,
                options: $request->getOptions()->withRole($context->role),
                paginationOptions: $request->getPaginationOptions(),
            );
        }

        if ($request instanceof AbstractRequest && $context->client !== null) {
            $request->setClient($context->client);
            $request = $request->withRole($context->role);
        }

        return $request;
    }

    private function normalizeRequests(): RequestCollection
    {
        return $this->requests instanceof RequestCollection
            ? $this->requests
            : RequestCollection::make($this->requests);
    }

    private function resolveRequestItem(mixed $item, BatchContext $context, int $index): RequestInterface
    {
        foreach ($this->requestResolvers as $resolver) {
            if (!$resolver->supports($item)) {
                continue;
            }

            $resolvedRequest = $resolver->resolve($item, $context);
            if (!$resolvedRequest instanceof RequestInterface) {
                throw new ConfigurationException(
                    $this->errorFactory->invalidItemMessage('batch', $index, $item),
                );
            }

            return $resolvedRequest;
        }

        throw new ConfigurationException(
            $this->errorFactory->unsupportedItemMessage('batch', $index, $item),
        );
    }

    private function buildContext(): BatchContext
    {
        return new BatchContext(
            client: $this->client,
            mode: $this->mode,
            failStrategy: $this->failStrategy,
            concurrency: $this->concurrency,
            parent: $this->parent,
            role: $this->role,
        );
    }

    private function resolveStrategy(ExecutionMode $mode): BatchStrategyInterface
    {
        return $mode === ExecutionMode::Sequential
            ? $this->sequentialStrategy
            : $this->parallelStrategy;
    }

    private function assertClient(BatchContext $context): void
    {
        if ($context->client === null) {
            throw new ConfigurationException('Не указан клиент для batch выполнения');
        }
    }

    /**
     * @param array<int, RequestInterface> $requests
     * @return array<int, ExecutionResult>
     */
    private function executeWithStrategy(
        BatchStrategyInterface $strategy,
        BatchContext $context,
        array $requests,
    ): array {
        return $strategy->execute(
            $context,
            $requests,
            fn (RequestInterface $request, Throwable $exception): ExecutionResult => $this->errorFactory->buildExceptionResult($request, $exception),
        );
    }

    private function buildBatchResult(ResultCollection $collection): BatchResult
    {
        $summary = $collection->summarize();
        $errors = $this->collectErrors($collection);
        $meta = $this->buildBatchMeta($summary);

        return new BatchResult(
            data: null,
            status: $summary->status,
            errors: new ErrorCollection($errors),
            meta: $meta,
            nested: $collection->all(),
        );
    }

    /**
     * @return array<int, RequestError>
     */
    private function collectErrors(ResultCollection $collection): array
    {
        $errors = [];
        foreach ($collection->failed()->all() as $result) {
            if ($result->errors->first() !== null) {
                $errors[] = $result->errors->first();
            }
        }

        return $errors;
    }

    private function buildBatchMeta(ResultSummary $summary): BatchMeta
    {
        return new BatchMeta(
            total: $summary->total,
            successful: $summary->successful,
            failed: $summary->failed,
            partial: $summary->partial,
        );
    }
}
