<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Traits;

use Brahmic\ApiSutra\Attributes\Behavior\Cache;
use Brahmic\ApiSutra\Attributes\Behavior\Execution;
use Brahmic\ApiSutra\Attributes\Behavior\Idempotent;
use Brahmic\ApiSutra\Attributes\Behavior\Pagination;
use Brahmic\ApiSutra\Attributes\Behavior\RateLimit;
use Brahmic\ApiSutra\Attributes\Behavior\Retry;
use Brahmic\ApiSutra\Attributes\Behavior\Timeout;
use Brahmic\ApiSutra\Attributes\Request\AuthScope;
use Brahmic\ApiSutra\Attributes\Request\OperationDescriptor;
use Brahmic\ApiSutra\Attributes\Response\ContinuationResult;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Enums\Http\HttpMethod;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;

/**
 * Доступ к атрибутам RequestSpec.
 */
trait RequestSpecAccessorsTrait
{
    #[\Override]
    public function getMethod(): HttpMethod
    {
        $method = $this->spec()->method;
        if ($method === null) {
            throw new ConfigurationException('HTTP метод не задан');
        }

        return $method;
    }

    #[\Override]
    public function getEndpoint(): string
    {
        $resolved = $this->resolveEndpoint();
        if ($resolved !== null) {
            return $resolved;
        }

        $endpoint = $this->spec()->endpoint;
        if ($endpoint === null) {
            throw new ConfigurationException('Endpoint не задан');
        }

        return $endpoint;
    }

    #[\Override]
    public function getResponseType(): ?string
    {
        return $this->spec()->responseType;
    }

    public function getReturnsAttribute(): ?Returns
    {
        return $this->spec()->returns;
    }

    public function getContinuationResultAttribute(): ?ContinuationResult
    {
        return $this->spec()->continuationResult;
    }

    public function getOperationDescriptorAttribute(): ?OperationDescriptor
    {
        return $this->spec()->operationDescriptor;
    }

    public function getOperationTitle(): ?string
    {
        return $this->spec()->operationDescriptor?->title;
    }

    public function getOperationDescription(): ?string
    {
        return $this->spec()->operationDescriptor?->description;
    }

    public function getOperationNote(): ?string
    {
        return $this->spec()->operationDescriptor?->note;
    }

    public function hasDownload(): bool
    {
        return $this->spec()->hasDownload;
    }

    public function hasNoAuth(): bool
    {
        return $this->spec()->hasNoAuth;
    }

    public function hasSkipCredentialsEnrichment(): bool
    {
        return $this->spec()->skipCredentialsEnrichment;
    }

    public function getAuthScopeAttribute(): ?AuthScope
    {
        return $this->spec()->authScope;
    }

    public function getAuthScope(): ?string
    {
        return $this->spec()->authScope?->scope;
    }

    public function getCacheAttribute(): ?Cache
    {
        return $this->spec()->cache;
    }

    public function getRetryAttribute(): ?Retry
    {
        return $this->spec()->retry;
    }

    public function getTimeoutAttribute(): ?Timeout
    {
        return $this->spec()->timeout;
    }

    public function hasRawResponse(): bool
    {
        return $this->spec()->hasRawResponse;
    }

    public function getIdempotentAttribute(): ?Idempotent
    {
        return $this->spec()->idempotent;
    }

    public function getExecutionAttribute(): ?Execution
    {
        return $this->spec()->execution;
    }

    public function getPaginationAttribute(): ?Pagination
    {
        return $this->spec()->pagination;
    }

    public function getRateLimitAttribute(): ?RateLimit
    {
        return $this->spec()->rateLimit;
    }
}
