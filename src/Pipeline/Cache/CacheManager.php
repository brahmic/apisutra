<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pipeline\Cache;

use BackedEnum;
use Brahmic\ApiSutra\Config\CacheConfig;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Cache\CacheMode;
use Brahmic\ApiSutra\Enums\Execution\RequestRole;
use Brahmic\ApiSutra\Pipeline\Preparation\PreparedRequestFactory;
use Brahmic\ApiSutra\Pipeline\Preparation\RequestPreparer;
use Brahmic\ApiSutra\Request\PaginationOptions;
use Brahmic\ApiSutra\Request\RequestOptions;
use Brahmic\ApiSutra\VO\Cache\CacheOverride;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

/**
 * Менеджер кеша на уровне пайплайна.
 *
 * Нюансы:
 * - override опций имеет приоритет над атрибутом и конфигом;
 * - download кешируется только при явном opt-in;
 * - upload с файлами не кешируется;
 * - ключ строится из метода, baseUrl, query и body (query нормализуется);
 * - clearCache использует тот же ключ, что и чтение/запись.
 */
final readonly class CacheManager
{
    public function __construct(
        private ClientConfig $config,
        private PreparedRequestFactory $preparedRequestFactory,
        private RequestPreparer $requestPreparer,
    ) {}

    public function clearCache(
        RequestInterface $request,
        ?string $traceId,
        ?RequestOptions $options = null,
        ?PaginationOptions $paginationOptions = null,
    ): void
    {
        $state = $this->resolveCacheState($request, $options);
        if ($state === null) {
            return;
        }
        [$cache, $override] = $state;

        if ($this->isDownloadCacheBlocked($request, $override->mode)) {
            return;
        }

        // Подготовка учитывает overrides и пагинацию для совпадения ключа.
        $prepared = $this->prepareForCache($request, $traceId, $options, $paginationOptions);
        if ($this->isFileUpload($prepared)) {
            return;
        }
        $cacheKey = $this->buildCacheKey($prepared, $cache->prefix, $request);
        $cache->store->delete($cacheKey);
    }

    public function checkCache(RequestInterface $request, PipelineContext $context): ?ProviderResponse
    {
        $state = $this->resolveCacheState($request, $context->options);
        if ($state === null) {
            return null;
        }
        [$cache, $override] = $state;

        if ($this->isDownloadCacheBlocked($request, $override->mode)) {
            return null;
        }
        if (!$this->isReadEnabled($this->resolveCacheMode($cache, $override->mode))) {
            return null;
        }

        if ($this->isFileUpload($context->preparedRequest)) {
            return null;
        }
        $cacheKey = $this->buildCacheKey($context->preparedRequest, $cache->prefix, $request);
        $cached = $cache->store->get($cacheKey);
        if (!is_array($cached)) {
            return null;
        }

        return $this->buildCachedResponse($cached, $context);
    }

    public function storeCache(RequestInterface $request, PipelineContext $context): void
    {
        $state = $this->resolveCacheState($request, $context->options);
        if ($state === null) {
            return;
        }
        [$cache, $override] = $state;

        if ($this->isDownloadCacheBlocked($request, $override->mode)) {
            return;
        }
        if (!$this->isWriteEnabled($this->resolveCacheMode($cache, $override->mode))) {
            return;
        }

        if ($this->isFileUpload($context->preparedRequest)) {
            return;
        }
        $ttl = $override->ttl ?? $cache->ttl;
        $cacheKey = $this->buildCacheKey($context->preparedRequest, $cache->prefix, $request);

        $cache->store->set($cacheKey, [
            'status' => $context->response?->status ?? 200,
            'headers' => $context->response?->headers ?? [],
            'body' => $context->response?->body ?? '',
        ], $ttl);
    }

    private function resolveCacheConfig(RequestInterface $request): ?CacheConfig
    {
        $cacheConfig = $this->config->cacheConfig;
        if ($cacheConfig === null && $this->config->cache !== null) {
            $cacheConfig = new CacheConfig(store: $this->config->cache);
        }

        if ($request instanceof AbstractRequest) {
            $attribute = $request->getCacheAttribute();
            if ($attribute !== null) {
                return new CacheConfig(
                    store: $cacheConfig?->store,
                    ttl: $attribute->ttl ?? $cacheConfig?->ttl ?? 3600,
                    prefix: $cacheConfig?->prefix ?? '',
                    mode: $attribute->mode,
                );
            }
        }

        return $cacheConfig;
    }

    private function prepareForCache(
        RequestInterface $request,
        ?string $traceId,
        ?RequestOptions $options,
        ?PaginationOptions $paginationOptions,
    ): PreparedRequest
    {
        $resolvedTraceId = $this->requestPreparer->resolveTraceId($request, null, $traceId, $options);
        $context = new PipelineContext(
            request: $request,
            config: $this->config,
            traceId: $resolvedTraceId,
            role: RequestRole::Root,
            options: $options,
            paginationOptions: $paginationOptions,
        );

        return $this->preparedRequestFactory->create($request, $context);
    }

    private function resolveCacheOverride(RequestInterface $request, ?RequestOptions $options): CacheOverride
    {
        if ($options !== null) {
            return $options->getCacheOverride();
        }

        if (!$request instanceof AbstractRequest) {
            return CacheOverride::empty();
        }

        return $request->getCacheOverride();
    }

    /**
     * @return array{0: CacheConfig, 1: CacheOverride}|null
     */
    private function resolveCacheState(RequestInterface $request, ?RequestOptions $options): ?array
    {
        $cache = $this->resolveCacheWithStore($request);
        if ($cache === null) {
            return null;
        }

        return [$cache, $this->resolveCacheOverride($request, $options)];
    }

    private function resolveCacheMode(CacheConfig $cache, ?CacheMode $overrideMode): CacheMode
    {
        return $overrideMode ?? $cache->mode;
    }

    private function isReadEnabled(CacheMode $mode): bool
    {
        return match ($mode) {
            CacheMode::Enabled, CacheMode::ReadOnly => true,
            CacheMode::Disabled, CacheMode::WriteOnly => false,
        };
    }

    private function isWriteEnabled(CacheMode $mode): bool
    {
        return match ($mode) {
            CacheMode::Enabled, CacheMode::WriteOnly => true,
            CacheMode::Disabled, CacheMode::ReadOnly => false,
        };
    }

    private function hasStore(?CacheConfig $cache): bool
    {
        return $cache !== null && $cache->store !== null;
    }

    private function resolveCacheWithStore(RequestInterface $request): ?CacheConfig
    {
        $cache = $this->resolveCacheConfig($request);
        if (!$this->hasStore($cache)) {
            return null;
        }

        return $cache;
    }

    /**
     * @param array<string, mixed> $cached
     */
    private function buildCachedResponse(array $cached, PipelineContext $context): ProviderResponse
    {
        return new ProviderResponse(
            status: $cached['status'] ?? 200,
            headers: $cached['headers'] ?? [],
            body: $cached['body'] ?? '',
            request: $context->preparedRequest,
            duration: 0,
        );
    }

    private function isFileUpload(PreparedRequest $request): bool
    {
        // Upload с файлами не кешируется, чтобы исключить коллизии ключей.
        if ($request->stream !== null) {
            return true;
        }

        $files = $request->meta['files'] ?? null;
        return is_array($files) && $files !== [];
    }

    private function isDownloadRequest(RequestInterface $request): bool
    {
        return $request instanceof AbstractRequest && $request->hasDownload();
    }

    private function isDownloadCacheBlocked(RequestInterface $request, ?CacheMode $overrideMode): bool
    {
        return $this->isDownloadRequest($request)
            && !$this->isDownloadCacheAllowed($request, $overrideMode);
    }

    private function isDownloadCacheAllowed(RequestInterface $request, ?CacheMode $overrideMode): bool
    {
        if ($overrideMode !== null) {
            return $overrideMode !== CacheMode::Disabled;
        }

        if (!$request instanceof AbstractRequest) {
            return false;
        }

        $attribute = $request->getCacheAttribute();
        return $attribute !== null && $attribute->mode !== CacheMode::Disabled;
    }

    private function buildCacheKey(PreparedRequest $request, string $prefix, RequestInterface $original): string
    {
        if ($original instanceof AbstractRequest) {
            $attribute = $original->getCacheAttribute();
            if ($attribute !== null && $attribute->key !== null) {
                // Явный ключ из атрибута имеет приоритет над расчётным.
                return $prefix . $attribute->key;
            }
        }

        $algo = in_array('xxh3', hash_algos(), true) ? 'xxh3' : 'sha256';
        $bodyHash = $request->body !== null ? hash($algo, $request->body) : '';
        $urlParts = explode('?', $request->url, 2);
        $baseUrl = $urlParts[0] ?? $request->url;
        $queryHash = '';
        if (isset($request->meta['query']) && is_array($request->meta['query'])) {
            // Нормализуем query для стабильного ключа.
            $queryHash = hash($algo, $this->normalizeQueryForCache($request->meta['query']));
        }
        $parts = [$request->method->value, $baseUrl, $queryHash, $bodyHash];
        $hash = hash($algo, implode('|', $parts));

        return $prefix . $hash;
    }

    /**
     * @param array<string, array{value: mixed, format: mixed}|mixed> $query
     */
    private function normalizeQueryForCache(array $query): string
    {
        // Сортируем ключи и значения массивов, чтобы избежать коллизий.
        ksort($query);
        $parts = [];
        foreach ($query as $name => $data) {
            $value = is_array($data) && array_key_exists('value', $data) ? $data['value'] : $data;
            $format = is_array($data) && array_key_exists('format', $data) ? $data['format'] : null;

            if (is_array($value)) {
                $normalized = array_map(static fn (mixed $item) => (string) $item, $value);
                sort($normalized);
                $value = $normalized;
            }

            $formatValue = $format instanceof BackedEnum ? $format->value : (string) $format;
            $parts[] = $name . '|' . $formatValue . '|' . json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        sort($parts);

        return implode('&', $parts);
    }
}
