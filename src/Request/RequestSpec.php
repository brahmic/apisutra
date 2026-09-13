<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Request;

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

final readonly class RequestSpec
{
    public function __construct(
        public ?HttpMethod $method,
        public ?string $endpoint,
        public ?string $responseType,
        public ?Returns $returns,
        public ?ContinuationResult $continuationResult,
        public ?OperationDescriptor $operationDescriptor,
        public ?Cache $cache,
        public ?Retry $retry,
        public ?Timeout $timeout,
        public ?Idempotent $idempotent,
        public ?Execution $execution,
        public ?Pagination $pagination,
        public ?RateLimit $rateLimit,
        public ?AuthScope $authScope,
        public bool $hasNoAuth,
        public bool $skipCredentialsEnrichment,
        public bool $hasDownload,
        public bool $hasRawResponse = false,
    ) {
    }
}
