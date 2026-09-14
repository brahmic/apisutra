<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pipeline\Execution;

use Brahmic\ApiSutra\Collections\ErrorCollection;
use Brahmic\ApiSutra\Collections\ResultCollection;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\CompositeRequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\DependsOnRequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Pipeline\PipelineExecutorInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Errors\ErrorCode;
use Brahmic\ApiSutra\Enums\Execution\ExecutionMode;
use Brahmic\ApiSutra\Enums\Execution\FailStrategy;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Execution\CompositeExecutor;
use Brahmic\ApiSutra\Execution\DependsOnExecutor;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\VO\Errors\RequestError;
use Brahmic\ApiSutra\VO\Errors\SystemErrorContextBuilder;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class CompositeFlow
{
    public function __construct(
        private Hydrator $hydrator,
        private PipelineExecutorInterface $executor,
    ) {
    }

    public function executeComposite(
        CompositeRequestInterface $request,
        PipelineContext $context,
    ): ExecutionResult {
        [$mode, $strategy] = $this->resolveExecutionConfig($request);
        $resultCollection = $this->executeCompositeRequests($request, $context, $mode, $strategy);

        if ($this->shouldFail($strategy, $resultCollection)) {
            return $this->buildCompositeResult($request, $context, $resultCollection, ResultStatus::FAILED);
        }

        $context->budget?->check('composite');
        $data = $this->aggregateCompositeData($request, $resultCollection, $context);
        $status = $this->resolveCompositeStatus($resultCollection);

        return $this->buildCompositeResult($request, $context, $resultCollection, $status, $data);
    }

    public function executeDependsOn(
        DependsOnRequestInterface $request,
        PipelineContext $context,
    ): ExecutionResult {
        [$mode, $strategy] = $this->resolveExecutionConfig($request);
        $resultCollection = $this->executeDependencies($request, $context, $mode, $strategy);

        if ($this->shouldFail($strategy, $resultCollection)) {
            return $this->buildCompositeResult($request, $context, $resultCollection, ResultStatus::FAILED);
        }

        $context->budget?->check('dependencies');
        $this->processDependencies($request, $resultCollection, $context);
        $context->budget?->check('dependencies');

        return $this->executeMainRequest($request, $context);
    }

    private function resolveExecutionConfig(RequestInterface $request): array
    {
        $execution = $request instanceof AbstractRequest
            ? $request->getExecutionAttribute()
            : null;

        return [
            $execution->mode ?? ExecutionMode::Sequential,
            $execution->failStrategy ?? FailStrategy::FailAll,
        ];
    }

    private function resolveClient(PipelineContext $context, string $message): ClientInterface
    {
        $client = $context->request instanceof AbstractRequest ? $context->request->getClient() : null;
        if ($client === null) {
            throw new ConfigurationException($message);
        }

        return $client;
    }

    private function executeCompositeRequests(
        CompositeRequestInterface $request,
        PipelineContext $context,
        ExecutionMode $mode,
        FailStrategy $strategy,
    ): ResultCollection {
        $client = $this->resolveClient($context, 'Не указан клиент для composite выполнения');
        $executor = new CompositeExecutor(
            client: $client,
            requests: $request->requests(),
            parent: $context,
        );

        return $executor->execute($mode, $strategy);
    }

    private function executeDependencies(
        DependsOnRequestInterface $request,
        PipelineContext $context,
        ExecutionMode $mode,
        FailStrategy $strategy,
    ): ResultCollection {
        $client = $this->resolveClient($context, 'Не указан клиент для depends-on выполнения');
        $executor = new DependsOnExecutor(
            client: $client,
            requests: $request->dependencies(),
            parent: $context,
        );

        return $executor->execute($mode, $strategy);
    }

    private function shouldFail(FailStrategy $strategy, ResultCollection $results): bool
    {
        return $strategy === FailStrategy::FailAll && $results->hasErrors();
    }

    private function resolveCompositeStatus(ResultCollection $results): ResultStatus
    {
        return $results->hasErrors() ? ResultStatus::PARTIAL : ResultStatus::SUCCESS;
    }

    private function aggregateCompositeData(
        CompositeRequestInterface $request,
        ResultCollection $results,
        PipelineContext $context,
    ): mixed {
        return $request->aggregate($results, $context);
    }

    private function processDependencies(
        DependsOnRequestInterface $request,
        ResultCollection $results,
        PipelineContext $context,
    ): void {
        $request->processDependencies($results, $context);
    }

    private function executeMainRequest(
        DependsOnRequestInterface $request,
        PipelineContext $context,
    ): ExecutionResult {
        return $this->executor->execute(
            request: $request,
            role: $context->role,
            parent: $context,
            traceId: $context->traceId,
            skipComposite: true,
            skipValidation: true,
        );
    }

    private function buildCompositeResult(
        RequestInterface $request,
        PipelineContext $context,
        ResultCollection $results,
        ResultStatus $status,
        mixed $data = null,
    ): ExecutionResult {
        $errors = $this->collectErrors($results, $request, $context);
        $nested = $results->all();
        $meta = $results->toBatchMeta();
        $resultData = $this->hydrateResultData($request, $context, $data);

        return new ExecutionResult(
            data: $resultData,
            status: $status,
            errors: new ErrorCollection($errors),
            traceId: $context->traceId,
            meta: $meta,
            nested: $nested,
            requestClass: $request::class,
        );
    }

    private function collectErrors(
        ResultCollection $results,
        RequestInterface $request,
        PipelineContext $context,
    ): array {
        $errors = [];
        foreach ($results->all() as $result) {
            if ($result->isFailed()) {
                $firstError = $result->errors->first();
                if ($firstError !== null) {
                    $errors[] = $firstError;
                    continue;
                }

                $contextData = SystemErrorContextBuilder::build(
                    traceId: $context->traceId,
                    httpStatus: null,
                    requestClass: $request::class,
                );

                $errors[] = new RequestError(
                    code: ErrorCode::ServerError,
                    message: 'Ошибка вложенного запроса',
                    context: $contextData,
                    requestClass: $request::class,
                );
            }
        }

        return $errors;
    }

    private function hydrateResultData(RequestInterface $request, PipelineContext $context, mixed $data): mixed
    {
        if ($data === null || !$request instanceof AbstractRequest) {
            return $data;
        }

        $dtoType = $request->getResponseType();
        if ($dtoType === null) {
            return $data;
        }

        if ($context->config->hydrationRules !== null) {
            $context->hydrationSourceTransformed = true;
        }
        return $this->hydrator->hydrate($data, $dtoType, $context);
    }
}
