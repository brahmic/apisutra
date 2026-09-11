<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\VO\Pipeline;

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Errors\ErrorCode;
use Brahmic\ApiSutra\Pipeline\Cache\CacheExecutionState;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Enums\Execution\RequestRole;
use Brahmic\ApiSutra\Request\PaginationOptions;
use Brahmic\ApiSutra\Request\RequestOptions;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;

class PipelineContext
{
    public ?CacheExecutionState $cacheExecution = null;
    public ?ErrorCode $failureCode = null;
    public ?string $retryRefusalReason = null;

    public function __construct(
        public readonly RequestInterface $request,
        public readonly ClientConfig $config,
        public readonly string $traceId,
        public readonly RequestRole $role = RequestRole::Root,
        public readonly ?PipelineContext $parent = null,
        public readonly ?RequestOptions $options = null,
        public readonly ?PaginationOptions $paginationOptions = null,
        /**
         * @var array<string, mixed>|null
         */
        public ?array $requestContractDebug = null,
        public ?PreparedRequest $preparedRequest = null,
        public ?ProviderResponse $response = null,
        public ?object $dto = null,
    ) {}

    /**
     * Создать дочерний контекст (для nested/dependency)
     */
    public function child(RequestInterface $request, RequestRole $role): self
    {
        return new self(
            request: $request,
            config: $this->config,
            traceId: $this->traceId,
            role: $role,
            parent: $this,
            options: null,
            paginationOptions: null,
        );
    }
}
