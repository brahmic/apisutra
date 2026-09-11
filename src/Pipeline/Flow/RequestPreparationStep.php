<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pipeline\Flow;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Pipeline\Diagnostics\AuditLogger;
use Brahmic\ApiSutra\Pipeline\Preparation\PreparedRequestFactory;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use Psr\Log\LogLevel;

final readonly class RequestPreparationStep
{
    public function __construct(
        private PreparedRequestFactory $preparedRequestFactory,
        private AuditLogger $auditLogger,
    ) {}

    public function prepare(RequestInterface $request, PipelineContext $context): PreparedRequest
    {
        $prepared = $this->preparedRequestFactory->create($request, $context);
        $context->preparedRequest = $prepared;

        $secretFields = $prepared->meta['credentialsEnrichment']['secretKeys'] ?? [];
        $this->auditLogger->log(LogLevel::DEBUG, 'HTTP запрос подготовлен', [
            'trace' => $context->traceId,
            'method' => $prepared->method->value,
            'url' => $prepared->url,
        ], is_array($secretFields) ? array_values(array_filter($secretFields, 'is_string')) : []);

        return $prepared;
    }
}
