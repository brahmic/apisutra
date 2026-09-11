<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Request;

use Brahmic\ApiSutra\Attributes\AttributeMetadataCache;
use Brahmic\ApiSutra\Attributes\Behavior\Cache;
use Brahmic\ApiSutra\Attributes\Behavior\Execution;
use Brahmic\ApiSutra\Attributes\Behavior\Idempotent;
use Brahmic\ApiSutra\Attributes\Behavior\NoAuth;
use Brahmic\ApiSutra\Attributes\Behavior\Pagination;
use Brahmic\ApiSutra\Attributes\Behavior\RateLimit;
use Brahmic\ApiSutra\Attributes\Behavior\Retry;
use Brahmic\ApiSutra\Attributes\Behavior\Timeout;
use Brahmic\ApiSutra\Attributes\Http\Delete;
use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Http\Patch;
use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Http\Put;
use Brahmic\ApiSutra\Attributes\Request\AuthScope;
use Brahmic\ApiSutra\Attributes\Request\OperationDescriptor;
use Brahmic\ApiSutra\Attributes\Request\SkipCredentialsEnrichment;
use Brahmic\ApiSutra\Attributes\Response\ContinuationResult;
use Brahmic\ApiSutra\Attributes\Response\Download;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Http\HttpMethod;
use ReflectionAttribute;
use ReflectionClass;

final class RequestSpecResolver
{
    /**
     * @var array<string, RequestSpec>
     */
    private static array $cache = [];

    /**
     * @param array<class-string, HttpMethod> $methodAttributes
     */
    private const array METHOD_ATTRIBUTES = [
        Get::class => HttpMethod::GET,
        Post::class => HttpMethod::POST,
        Put::class => HttpMethod::PUT,
        Patch::class => HttpMethod::PATCH,
        Delete::class => HttpMethod::DELETE,
    ];

    public function __construct(
        private readonly ?AttributeMetadataCache $metadataCache = null,
    ) {}

    public function resolve(AbstractRequest $request): RequestSpec
    {
        return $this->resolveClass($request::class);
    }

    /**
     * @param class-string $requestClass
     */
    public function resolveClass(string $requestClass): RequestSpec
    {
        $cacheKey = $this->cacheKey($requestClass);
        if ($this->canUseCache() && isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $reflection = new ReflectionClass($requestClass);
        $attributes = $this->getClassAttributes($reflection);

        $method = null;
        $endpoint = null;
        foreach (self::METHOD_ATTRIBUTES as $attributeClass => $httpMethod) {
            $attribute = $this->getAttribute($attributes, $attributeClass);
            if ($attribute === null) {
                continue;
            }

            $instance = $attribute->newInstance();
            $method = $httpMethod;
            $endpoint = $instance->path ?? null;
            break;
        }

        $returns = $this->getAttributeInstance($attributes, Returns::class);
        $spec = new RequestSpec(
            method: $method,
            endpoint: $endpoint,
            responseType: $returns?->response,
            returns: $returns,
            continuationResult: $this->getAttributeInstance($attributes, ContinuationResult::class),
            operationDescriptor: $this->getAttributeInstance($attributes, OperationDescriptor::class),
            cache: $this->getAttributeInstance($attributes, Cache::class),
            retry: $this->getAttributeInstance($attributes, Retry::class),
            timeout: $this->getAttributeInstance($attributes, Timeout::class),
            idempotent: $this->getAttributeInstance($attributes, Idempotent::class),
            execution: $this->getAttributeInstance($attributes, Execution::class),
            pagination: $this->getAttributeInstance($attributes, Pagination::class),
            rateLimit: $this->getAttributeInstance($attributes, RateLimit::class),
            authScope: $this->getAttributeInstance($attributes, AuthScope::class),
            hasNoAuth: $this->hasAttribute($attributes, NoAuth::class),
            skipCredentialsEnrichment: $this->hasAttribute($attributes, SkipCredentialsEnrichment::class),
            hasDownload: $this->hasAttribute($attributes, Download::class),
        );

        if ($this->canUseCache()) {
            self::$cache[$cacheKey] = $spec;
        }

        return $spec;
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * @return array<int, ReflectionAttribute>
     */
    private function getClassAttributes(ReflectionClass $reflection): array
    {
        $metadata = $this->metadataCache?->get($reflection->getName());
        if (is_array($metadata) && isset($metadata['class'])) {
            return array_map(
                static fn (array $item) => $item['attribute'],
                $metadata['class'],
            );
        }

        return $reflection->getAttributes();
    }

    /**
     * @param class-string $requestClass
     */
    private function cacheKey(string $requestClass): string
    {
        if ($this->metadataCache === null) {
            return $requestClass;
        }

        return $requestClass . '|' . spl_object_id($this->metadataCache);
    }

    private function canUseCache(): bool
    {
        if ($this->metadataCache === null) {
            return true;
        }

        return $this->metadataCache->isEnabled();
    }

    /**
     * @param array<int, ReflectionAttribute> $attributes
     */
    private function hasAttribute(array $attributes, string $attributeClass): bool
    {
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === $attributeClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, ReflectionAttribute> $attributes
     */
    private function getAttribute(array $attributes, string $attributeClass): ?ReflectionAttribute
    {
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === $attributeClass) {
                return $attribute;
            }
        }

        return null;
    }

    /**
     * @param array<int, ReflectionAttribute> $attributes
     */
    private function getAttributeInstance(array $attributes, string $attributeClass): ?object
    {
        return $this->getAttribute($attributes, $attributeClass)?->newInstance();
    }
}
