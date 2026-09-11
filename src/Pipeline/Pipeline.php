<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pipeline;

use Brahmic\ApiSutra\Attributes\AttributeRegistry;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Concurrency\RetryHandlerInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestOptionsProviderInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Pipeline\PipelineExecutorInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;
use Brahmic\ApiSutra\Core\AbstractClient;
use Brahmic\ApiSutra\Enums\Execution\RequestRole;
use Brahmic\ApiSutra\Exceptions\ControlFlow\EarlyReturnException;
use Brahmic\ApiSutra\Extensions\ExtensionRegistry;
use Brahmic\ApiSutra\Hooks\HookRegistry;
use Brahmic\ApiSutra\Pipeline\Attributes\StageProcessor;
use Brahmic\ApiSutra\Pipeline\Auth\AuthHandler;
use Brahmic\ApiSutra\Pipeline\Cache\CacheManager;
use Brahmic\ApiSutra\Pipeline\Diagnostics\AuditLogger;
use Brahmic\ApiSutra\Pipeline\Error\ErrorPolicy;
use Brahmic\ApiSutra\Pipeline\Execution\CompositeFlow;
use Brahmic\ApiSutra\Pipeline\Flow\ExecutionResultBuilder;
use Brahmic\ApiSutra\Pipeline\Flow\PipelineCompositeHandler;
use Brahmic\ApiSutra\Pipeline\Flow\PipelineContextFactory;
use Brahmic\ApiSutra\Pipeline\Flow\PipelineValidator;
use Brahmic\ApiSutra\Pipeline\Flow\RequestContractValidator;
use Brahmic\ApiSutra\Pipeline\Flow\RequestFlowRunner;
use Brahmic\ApiSutra\Pipeline\Flow\RequestPreparationStep;
use Brahmic\ApiSutra\Pipeline\Hooks\HookRunner;
use Brahmic\ApiSutra\Pipeline\Hydration\ResponseHydrator;
use Brahmic\ApiSutra\Pipeline\Preparation\PreparedRequestFactory;
use Brahmic\ApiSutra\Pipeline\Preparation\RequestPreparer;
use Brahmic\ApiSutra\Pipeline\Result\ResultFactory;
use Brahmic\ApiSutra\Pipeline\Transport\RetrySender;
use Brahmic\ApiSutra\RateLimiting\RateLimiter;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Serialization\Serializer;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Оркестратор жизненного цикла запроса с гарантией порядка этапов.
 *
 * Инварианты:
 * - вход может быть RequestInterface или RequestExecutionInterface;
 * - options извлекаются и из RequestExecutionInterface, и из RequestOptionsProviderInterface;
 * - validate/contracts/composite/preparation/flow выполняются в стабильном порядке;
 * - при throwOnErrors исключения поднимаются вверх, иначе конвертируются в ExecutionResult.
 *
 * @see docs/technical/pipeline.md
 * @see docs/technical/execution.md
 * @see docs/guides/request-pipeline.md
 */
final class Pipeline implements PipelineExecutorInterface
{
    private readonly RequestPreparer $requestPreparer;
    private readonly StageProcessor $stageProcessor;
    private readonly AuthHandler $authHandler;
    private readonly HookRunner $hookRunner;
    private readonly CacheManager $cacheManager;
    private readonly RetrySender $retrySender;
    private readonly ErrorPolicy $errorPolicy;
    private readonly ResponseHydrator $responseHydrator;
    private readonly ResultFactory $resultFactory;
    private readonly AuditLogger $auditLogger;
    private readonly CompositeFlow $compositeFlow;
    private readonly ExecutionResultBuilder $resultBuilder;
    private readonly PipelineContextFactory $contextFactory;
    private readonly PipelineValidator $validator;
    private readonly RequestContractValidator $requestContractValidator;
    private readonly PipelineCompositeHandler $compositeHandler;
    private readonly RequestPreparationStep $preparationStep;
    private readonly RequestFlowRunner $flowRunner;
    private readonly PreparedRequestFactory $preparedRequestFactory;

    /**
     * Собирает зависимости и компоненты пайплайна.
     */
    public function __construct(
        private readonly ClientConfig $config,
        private readonly TransportInterface $transport,
        private readonly Serializer $serializer,
        private readonly Hydrator $hydrator,
        private readonly HookRegistry $hooks,
        private readonly AttributeRegistry $attributes,
        private readonly ExtensionRegistry $extensions,
        private readonly RateLimiter $rateLimiter,
        private readonly RetryHandlerInterface $retryHandler,
        private readonly ?SleeperInterface $sleeper = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?AbstractClient $client = null,
        private ?string $traceId = null,
    ) {
        $this->requestPreparer = new RequestPreparer($this->config);
        $this->stageProcessor = new StageProcessor($this->attributes);
        $this->hookRunner = new HookRunner($this->hooks);
        $this->errorPolicy = new ErrorPolicy($this->client);
        $this->responseHydrator = new ResponseHydrator($this->config, $this->hydrator, $this->extensions);
        $this->resultFactory = new ResultFactory($this->errorPolicy);
        $this->auditLogger = new AuditLogger($this->config);
        $this->preparedRequestFactory = new PreparedRequestFactory($this->serializer, $this->requestPreparer);
        $this->authHandler = new AuthHandler($this->config, $this, $this->sleeper);
        $this->cacheManager = new CacheManager(
            $this->config,
            $this->preparedRequestFactory,
            $this->requestPreparer,
            $this->authHandler,
            $this->client !== null ? $this->client::class : self::class,
        );
        $this->retrySender = new RetrySender(
            config: $this->config,
            transport: $this->transport,
            retryHandler: $this->retryHandler,
            rateLimiter: $this->rateLimiter,
            hookRunner: $this->hookRunner,
            authHandler: $this->authHandler,
            errorPolicy: $this->errorPolicy,
            auditLogger: $this->auditLogger,
            client: $this->client,
            sleeper: $this->sleeper,
        );
        $this->compositeFlow = new CompositeFlow($this->hydrator, $this);
        $this->resultBuilder = new ExecutionResultBuilder($this->config, $this->auditLogger, $this->responseHydrator);
        $this->contextFactory = new PipelineContextFactory(
            config: $this->config,
            requestPreparer: $this->requestPreparer,
            stageProcessor: $this->stageProcessor,
            auditLogger: $this->auditLogger,
        );
        $this->validator = new PipelineValidator($this->resultBuilder);
        $this->requestContractValidator = new RequestContractValidator();
        $this->compositeHandler = new PipelineCompositeHandler($this->compositeFlow);
        $this->preparationStep = new RequestPreparationStep($this->preparedRequestFactory, $this->auditLogger);
        $this->flowRunner = new RequestFlowRunner(
            config: $this->config,
            stageProcessor: $this->stageProcessor,
            authHandler: $this->authHandler,
            hookRunner: $this->hookRunner,
            cacheManager: $this->cacheManager,
            retrySender: $this->retrySender,
            errorPolicy: $this->errorPolicy,
            responseHydrator: $this->responseHydrator,
            resultFactory: $this->resultFactory,
            auditLogger: $this->auditLogger,
            resultBuilder: $this->resultBuilder,
        );
    }

    /**
     * Устанавливает traceId по умолчанию.
     */
    public function setTraceId(?string $traceId): void
    {
        $this->traceId = $traceId;
    }

    /**
     * Выполняет полный пайплайн запроса и возвращает результат.
     */
    public function execute(
        RequestInterface $request,
        RequestRole $role = RequestRole::Root,
        ?PipelineContext $parent = null,
        ?string $traceId = null,
        bool $skipComposite = false,
        bool $skipValidation = false,
    ): ExecutionResult {
        $options = null;
        $paginationOptions = null;
        if ($request instanceof RequestExecutionInterface) {
            $options = $request->getOptions();
            $paginationOptions = $request->getPaginationOptions();
            $request = $request->getRequest();
        }
        if ($options === null && $request instanceof RequestOptionsProviderInterface) {
            $options = $request->getOptions();
        }

        $audit = [];
        $context = null;
        $startTime = microtime(true);

        try {
            $context = $this->contextFactory->create(
                request: $request,
                role: $role,
                parent: $parent,
                traceId: $traceId,
                pipelineTraceId: $this->traceId,
                options: $options,
                paginationOptions: $paginationOptions,
            );
            $startTime = $this->contextFactory->start($request, $context, $audit);

            return $this->runStages($request, $context, $audit, $startTime, $skipComposite, $skipValidation);
        } catch (Throwable $exception) {
            if ($this->config->throwOnErrors) {
                throw $exception;
            }

            if (!$context instanceof PipelineContext) {
                $resolvedTraceId = $this->requestPreparer->resolveTraceId(
                    $request,
                    $traceId,
                    $this->traceId,
                    $options,
                );
                $context = new PipelineContext(
                    request: $request,
                    config: $this->config,
                    traceId: $resolvedTraceId,
                    role: $role,
                    parent: $parent,
                    options: $options,
                    paginationOptions: $paginationOptions,
                );
            }

            return $this->resultBuilder->buildExceptionResult($request, $context, $audit, $startTime, $exception);
        }
    }

    private function runStages(
        RequestInterface $request,
        PipelineContext $context,
        array &$audit,
        float $startTime,
        bool $skipComposite,
        bool $skipValidation,
    ): ExecutionResult {
        if (!$skipValidation) {
            $validationResult = $this->validator->validate($request, $context, $audit, $startTime);
            if ($validationResult instanceof ExecutionResult) {
                return $validationResult;
            }

            $contractValidation = $this->requestContractValidator->validate($request);
            $context->requestContractDebug = $contractValidation->oneOfDebug;
            if ($contractValidation->failed()) {
                $violation = $contractValidation->violation;
                if ($violation === null) {
                    throw new RuntimeException('Ожидалась ошибка контракта запроса');
                }

                return $this->resultBuilder->buildRequestContractViolation(
                    request: $request,
                    context: $context,
                    audit: $audit,
                    startTime: $startTime,
                    violation: $violation,
                );
            }
        }

        $compositeResult = $this->compositeHandler->handle($request, $context, $skipComposite);
        if ($compositeResult instanceof ExecutionResult) {
            return $compositeResult;
        }

        $prepared = $this->preparationStep->prepare($request, $context);

        try {
            return $this->flowRunner->run($request, $context, $audit, $startTime, $prepared);
        } catch (EarlyReturnException $exception) {
            return $this->resultBuilder->buildEarlyReturnResult($request, $context, $audit, $startTime, $prepared, $exception);
        } catch (Throwable $exception) {
            return $this->resultBuilder->buildExceptionResult($request, $context, $audit, $startTime, $exception);
        }
    }

    /**
     * Асинхронная отправка с тем же пайплайном.
     */
    public function executeAsync(
        RequestInterface $request,
        RequestRole $role = RequestRole::Root,
        ?PipelineContext $parent = null,
        ?string $traceId = null,
    ): PromiseInterface {
        $promise = new Promise();

        try {
            $promise->resolve($this->execute($request, $role, $parent, $traceId));
        } catch (Throwable $exception) {
            $promise->reject($exception);
        }

        return $promise;
    }

    /** Инвалидировать настроенное пространство кеша клиента. */
    public function clearCacheScope(): void
    {
        $this->cacheManager->clearScope();
    }

    /**
     * Очистить кеш для конкретного запроса.
     */
    public function clearCache(RequestInterface $request): void
    {
        $options = null;
        $paginationOptions = null;
        if ($request instanceof RequestExecutionInterface) {
            $options = $request->getOptions();
            $paginationOptions = $request->getPaginationOptions();
            $request = $request->getRequest();
        }
        if ($options === null && $request instanceof RequestOptionsProviderInterface) {
            $options = $request->getOptions();
        }

        $this->cacheManager->clearCache($request, $this->traceId, $options, $paginationOptions);
    }
}
