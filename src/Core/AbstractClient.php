<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Core;

use Brahmic\ApiSutra\Attributes\AttributeMetadataCache;
use Brahmic\ApiSutra\Attributes\AttributeRegistry;
use Brahmic\ApiSutra\Casts\CastRegistry;
use Brahmic\ApiSutra\Collections\RequestCollection;
use Brahmic\ApiSutra\Config\BatchConfig;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Continuation\ContinuationService;
use Brahmic\ApiSutra\Contracts\Interfaces\Attributes\AttributeMetadataCacheProviderInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Cache\CacheAwareInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Catalog\ProviderCatalogInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Catalog\ProviderCatalogRegistryInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Catalog\RequestBoundProviderCatalogInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Concurrency\ConcurrencyResolverInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\ContextualClientInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestOptionsProviderInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Extensions\ExtensionInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Inventory\OperationInventoryInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Inventory\ResponseDtoCatalogProviderInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Execution\RequestRole;
use Brahmic\ApiSutra\Enums\Execution\SendMode;
use Brahmic\ApiSutra\Execution\BatchExecutor;
use Brahmic\ApiSutra\Execution\PoolExecutor;
use Brahmic\ApiSutra\Extensions\ExtensionRegistry;
use Brahmic\ApiSutra\Hooks\HookRegistry;
use Brahmic\ApiSutra\OperationInventory\Catalog\ResponseDtoCatalog;
use Brahmic\ApiSutra\OperationInventory\OperationInventoryBuilder;
use Brahmic\ApiSutra\Pagination\PaginationRule;
use Brahmic\ApiSutra\Pipeline\Pipeline;
use Brahmic\ApiSutra\RateLimiting\RateLimiter;
use Brahmic\ApiSutra\Request\RequestResolver;
use Brahmic\ApiSutra\Request\RequestSpecResolver;
use Brahmic\ApiSutra\Resolver\ClassMapProvider;
use Brahmic\ApiSutra\Resolver\RequestScanner;
use Brahmic\ApiSutra\Response\ClientResponse;
use Brahmic\ApiSutra\Response\ClientResponseFactory;
use Brahmic\ApiSutra\Response\ClientResponseFactoryInterface;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\Result\ResolvedResultFactory;
use Brahmic\ApiSutra\Result\ResolvedResultFactoryInterface;
use Brahmic\ApiSutra\Result\ResolvedResultInterface;
use Brahmic\ApiSutra\Result\ResultHandle;
use Brahmic\ApiSutra\Retry\RetryHandler;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Serialization\Serializer;
use Brahmic\ApiSutra\Testing\MockClient;
use Brahmic\ApiSutra\Timing\SystemClock;
use Brahmic\ApiSutra\Timing\SystemSleeper;
use Brahmic\ApiSutra\Traits\DefaultRequestFailurePolicyTrait;
use Brahmic\ApiSutra\Traits\TestingClientTrait;
use Brahmic\ApiSutra\VO\Errors\ClientErrorMapperAwareInterface;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

abstract class AbstractClient implements ContextualClientInterface, AttributeMetadataCacheProviderInterface, ResponseDtoCatalogProviderInterface
{
    use TestingClientTrait;
    use DefaultRequestFailurePolicyTrait;

    private readonly HookRegistry $hooks;
    private readonly AttributeRegistry $attributes;
    private readonly ExtensionRegistry $extensions;
    private readonly CastRegistry $casts;
    private readonly Hydrator $hydrator;
    private readonly Serializer $serializer;
    private Pipeline $pipeline;
    private readonly RateLimiter $rateLimiter;
    private readonly LoggerInterface $logger;
    private ?string $traceId = null;
    private readonly SleeperInterface $sleeper;
    private readonly AttributeMetadataCache $metadataCache;
    private readonly RequestResolver $requestResolver;
    private readonly ResolvedResultFactoryInterface $resolvedResultFactory;
    private readonly ClientResponseFactoryInterface $clientResponseFactory;
    private ?ContinuationService $continuationService = null;
    private ?OperationInventoryInterface $operationInventory = null;
    private ?ResponseDtoCatalog $responseDtoCatalog = null;

    public function __construct(
        private readonly ClientConfig $config,
        private TransportInterface $transport,
        ?HookRegistry $hooks = null,
        ?AttributeRegistry $attributes = null,
        ?ExtensionRegistry $extensions = null,
        ?SleeperInterface $sleeper = null,
        private readonly ClockInterface $clock = new SystemClock(),
    ) {
        $this->logger = $config->logger ?? new NullLogger();
        $this->sleeper = $sleeper ?? new SystemSleeper();

        $this->metadataCache = $this->buildMetadataCache($config);
        $this->hooks = $hooks ?? new HookRegistry();
        $this->attributes = $attributes ?? new AttributeRegistry(cache: $this->metadataCache);
        $this->casts = new CastRegistry();
        $this->registerCasts($config);
        $this->extensions = $this->buildExtensionRegistry($extensions);
        $this->registerExtensions($config);
        $this->configureAuthCache($config);
        $this->applyGlobalMockTransport();
        $this->hydrator = new Hydrator($this->casts, $this->metadataCache, rules: $config->hydrationRules);
        $this->serializer = new Serializer($this->casts, $this->metadataCache, $config->hydrationRules);
        $this->rateLimiter = new RateLimiter(clock: $this->clock, sleeper: $this->sleeper, backend: $config->rateLimitBackend);
        $this->pipeline = $this->buildPipeline();

        $this->resolvedResultFactory = $config->resolvedResultFactory
            ?? new ResolvedResultFactory(
                $config->errorMapper,
                $config->errorContextFactory,
                $config->continuationTokenExtractor,
            );
        $this->clientResponseFactory = $this->resolveResponseFactory($config);
        $this->requestResolver = new RequestResolver(
            config: $this->config,
            executor: fn (RequestInterface $request) => $this->execute($request),
        );
    }

    #[\Override]
    public function send(RequestInterface $request, SendMode $mode = SendMode::Sync): ResultHandle
    {
        if ($mode === SendMode::Async) {
            return $this->sendAsync($request);
        }

        $result = $this->attachResultMeta(
            $this->requestResolver->resolve($request),
        );
        return new ResultHandle($result, $this->resolvedResultFactory, $this, $request);
    }

    /** Вложенное выполнение сохраняет пагинацию и метаданные результата. */
    public function sendInContext(RequestInterface $request, PipelineContext $parent, RequestRole $role, SendMode $mode = SendMode::Sync): ResultHandle
    {
        $resolver = new RequestResolver(
            config: $this->config,
            executor: fn (RequestInterface $item): ExecutionResult => $this->pipeline->execute($item, $role, $parent, $parent->traceId),
        );
        if ($mode === SendMode::Sync) {
            return new ResultHandle($this->attachResultMeta($resolver->resolve($request)), $this->resolvedResultFactory, $this, $request);
        }
        if ($this->resolvePaginationRule($request)->isSingle()) {
            $promise = $this->pipeline->executeAsync($request, $role, $parent, $parent->traceId)->then(
                fn (ExecutionResult $result): ExecutionResult => $this->attachResultMeta($result),
            );
        } else {
            $promise = new Promise();
            try {
                $promise->resolve($this->attachResultMeta($resolver->resolve($request)));
            } catch (Throwable $exception) {
                $promise->reject($exception);
            }
        }
        return new ResultHandle($promise, $this->resolvedResultFactory, $this, $request);
    }

    #[\Override]
    /**
     * Асинхронная отправка запроса.
     *
     * Нюансы поведения:
     * - Для одиночных запросов используется `executeAsync()` и возвращается Promise.
     * - Для пагинации выполнение остаётся синхронным: полный resolve/execute делается сразу,
     *   а результат только оборачивается в Promise (то есть реальной параллельности нет).
     * - Это связано с зависимостью страниц от meta (hasMore/nextCursor/totalPages) и guard-проверок.
     * - Для настоящей асинхронности используйте batch/pool или независимые одиночные запросы.
     * - При `throwOnErrors=true` исключение попадает в Promise rejection.
     *
     * Отложено на будущее:
     * Полноценный async‑пайплайн — единственный честный способ сделать пагинацию реально асинхронной.
     * Но это крупный рефактор, затрагивающий retry, хуки, кэш, hydration, error‑handling и тесты.
     * По сути — новый режим исполнения, высокий риск и почти неизбежные поведенческие изменения.
     */
    public function sendAsync(RequestInterface $request): ResultHandle
    {
        $rule = $this->resolvePaginationRule($request);

        if ($rule->isSingle()) {
            return new ResultHandle(
                $this->executeAsync($request)->then(
                    fn (ExecutionResult $result): ExecutionResult => $this->attachResultMeta($result),
                ),
                $this->resolvedResultFactory,
                $this,
                $request,
            );
        }

        $promise = new Promise();
        try {
            $promise->resolve(
                $this->attachResultMeta(
                    $this->requestResolver->resolve($request),
                ),
            );
        } catch (Throwable $exception) {
            $promise->reject($exception);
        }

        return new ResultHandle($promise, $this->resolvedResultFactory, $this, $request);
    }

    #[\Override]
    public function response(ResolvedResultInterface $result): ClientResponse
    {
        return $this->clientResponseFactory->make($result);
    }

    public function batch(RequestCollection|array $requests, ?BatchConfig $config = null): BatchExecutor
    {
        if ($config !== null) {
            return BatchExecutor::fromConfig($this, $requests, $config);
        }

        return new BatchExecutor(client: $this, requests: $requests);
    }

    public function pool(
        iterable $requests,
        int|callable|ConcurrencyResolverInterface $concurrency = 5,
    ): PoolExecutor {
        return new PoolExecutor($this, $requests, $concurrency, $this->config->pool);
    }

    #[\Override]
    public function getAttributeMetadataCache(): AttributeMetadataCache
    {
        return $this->metadataCache;
    }

    protected function execute(RequestInterface $request): ExecutionResult
    {
        return $this->pipeline->execute($request, traceId: $this->traceId);
    }

    protected function executeAsync(RequestInterface $request): PromiseInterface
    {
        return $this->pipeline->executeAsync($request, traceId: $this->traceId);
    }

    private function resolvePaginationRule(RequestInterface $request): PaginationRule
    {
        $options = null;

        if ($request instanceof RequestExecutionInterface) {
            $options = $request->getOptions();
            $request = $request->getRequest();
        }

        if ($options === null && $request instanceof RequestOptionsProviderInterface) {
            $options = $request->getOptions();
        }

        return $this->requestResolver->resolveRule($options);
    }

    /**
     * Применяет provider ResultMeta extractor к результату, если это допустимо.
     */
    private function attachResultMeta(ExecutionResult $result): ExecutionResult
    {
        if ($result->meta !== null) {
            return $result;
        }

        $extractor = $this->config->resultMetaExtractor;
        if ($extractor === null) {
            return $result;
        }

        $meta = $extractor->extract($result);
        if ($meta === null) {
            return $result;
        }

        return new ExecutionResult(
            data: $result->data,
            status: $result->status,
            errors: $result->errors,
            validationErrors: $result->validationErrors,
            debug: $result->debug,
            traceId: $result->traceId,
            audit: $result->audit,
            meta: $meta,
            nested: $result->nested,
            requestClass: $result->requestClass,
            response: $result->response,
            exception: $result->exception,
        );
    }

    private function resolveResponseFactory(ClientConfig $config): ClientResponseFactoryInterface
    {
        $factory = $config->responseFactory;
        if ($factory === null) {
            return new ClientResponseFactory($config->errorMapper);
        }

        if ($config->errorMapper !== null && $factory instanceof ClientErrorMapperAwareInterface) {
            return $factory->withErrorMapper($config->errorMapper);
        }

        return $factory;
    }

    public function clearCacheForRequest(RequestInterface $request): void
    {
        $this->pipeline->clearCache($request);
    }

    public function registerExtension(ExtensionInterface $extension): static
    {
        $this->extensions->register($extension);
        return $this;
    }

    public function getExtension(string $name): ?ExtensionInterface
    {
        return $this->extensions->get($name);
    }

    public function hooks(): HookRegistry
    {
        return $this->hooks;
    }

    #[\Override]
    public function getConfig(): ClientConfig
    {
        return $this->config;
    }

    public function setTraceId(string $traceId): void
    {
        $this->traceId = $traceId;
        $this->pipeline->setTraceId($traceId);
    }

    public function clearCache(): void
    {
        $this->pipeline->clearCacheScope();
    }

    public function continuation(): ContinuationService
    {
        if ($this->continuationService === null) {
            $this->continuationService = new ContinuationService($this, $this->hydrator);
        }

        return $this->continuationService;
    }

    public function operationInventory(): OperationInventoryInterface
    {
        if ($this->operationInventory === null) {
            $builder = new OperationInventoryBuilder(
                requestScanner: new RequestScanner(new ClassMapProvider()),
                requestSpecResolver: new RequestSpecResolver($this->metadataCache),
            );
            $this->operationInventory = $builder->buildForClient($this);
        }

        return $this->operationInventory;
    }

    #[\Override]
    public function responseDtoCatalog(): ResponseDtoCatalog
    {
        if ($this->responseDtoCatalog === null) {
            $this->responseDtoCatalog = new ResponseDtoCatalog($this->operationInventory());
        }

        return $this->responseDtoCatalog;
    }

    public function providerCatalogs(): ?ProviderCatalogRegistryInterface
    {
        return $this->config->providerCatalogRegistry;
    }

    public function providerCatalog(string $key): ?ProviderCatalogInterface
    {
        return $this->config->providerCatalogRegistry?->get($key);
    }

    /**
     * @return array<string, RequestBoundProviderCatalogInterface>
     */
    public function providerCatalogsForRequest(string $requestClass): array
    {
        return $this->config->providerCatalogRegistry?->forRequest($requestClass) ?? [];
    }

    public function hasRequestFailedInternal(ProviderResponse $response): bool
    {
        return $this->hasRequestFailed($response);
    }

    public function shouldRetryInternal(ProviderResponse $response, int $attempt): bool
    {
        return $this->shouldRetry($response, $attempt);
    }

    private function rebuildPipeline(): void
    {
        $this->pipeline = $this->buildPipeline();
    }

    public function getRequestExceptionInternal(ProviderResponse $response): ?Throwable
    {
        return $this->getRequestException($response);
    }

    private function buildPipeline(): Pipeline
    {
        return new Pipeline(
            config: $this->config,
            transport: $this->transport,
            serializer: $this->serializer,
            hydrator: $this->hydrator,
            hooks: $this->hooks,
            attributes: $this->attributes,
            extensions: $this->extensions,
            rateLimiter: $this->rateLimiter,
            retryHandler: new RetryHandler($this->transport),
            sleeper: $this->sleeper,
            logger: $this->logger,
            client: $this,
            clock: $this->clock,
        );
    }

    private function buildMetadataCache(ClientConfig $config): AttributeMetadataCache
    {
        $cacheEnabled = $config->environment !== Environment::Local
            && $config->environment !== Environment::Testing;

        return new AttributeMetadataCache($cacheEnabled);
    }

    private function buildExtensionRegistry(?ExtensionRegistry $extensions): ExtensionRegistry
    {
        return $extensions ?? new ExtensionRegistry(
            casts: $this->casts,
            hooks: $this->hooks,
            attributes: $this->attributes,
        );
    }

    private function registerCasts(ClientConfig $config): void
    {
        foreach ($config->casts as $type => $cast) {
            $this->casts->register($type, $cast);
        }
    }

    private function registerExtensions(ClientConfig $config): void
    {
        foreach ($config->extensions as $extension) {
            if ($extension instanceof ExtensionInterface) {
                $this->extensions->register($extension);
            }
        }
    }

    private function configureAuthCache(ClientConfig $config): void
    {
        if ($config->auth instanceof CacheAwareInterface) {
            $cache = $config->cache ?? $config->cacheConfig?->store;
            if ($cache !== null) {
                $config->auth->setCache($cache);
            }
        }
    }

    private function applyGlobalMockTransport(): void
    {
        if (MockClient::hasGlobal()) {
            $this->transport = MockClient::global();
        }
    }
}
