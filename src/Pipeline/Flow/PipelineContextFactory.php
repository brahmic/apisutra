<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pipeline\Flow;

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Execution\RequestRole;
use Brahmic\ApiSutra\Enums\Pipeline\PipelineStage;
use Brahmic\ApiSutra\Pipeline\Attributes\StageProcessor;
use Brahmic\ApiSutra\Pipeline\Diagnostics\AuditLogger;
use Brahmic\ApiSutra\Pipeline\Preparation\RequestPreparer;
use Brahmic\ApiSutra\Request\PaginationOptions;
use Brahmic\ApiSutra\Request\RequestOptions;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use Psr\Log\LogLevel;

/**
 * Фабрика контекста пайплайна и стартовой телеметрии запроса.
 *
 * Инварианты:
 * - role может быть переопределён runtime options (и/или request-level override);
 * - traceId всегда нормализуется через RequestPreparer;
 * - для AbstractRequest контекст фиксируется в экземпляре запроса.
 *
 * @see docs/technical/pipeline.md
 * @see docs/guides/request-pipeline.md
 */
final readonly class PipelineContextFactory
{
    public function __construct(
        private ClientConfig $config,
        private RequestPreparer $requestPreparer,
        private StageProcessor $stageProcessor,
        private AuditLogger $auditLogger,
    ) {}

    public function create(
        RequestInterface $request,
        RequestRole $role,
        ?PipelineContext $parent,
        ?string $traceId,
        ?string $pipelineTraceId,
        ?RequestOptions $options = null,
        ?PaginationOptions $paginationOptions = null,
    ): PipelineContext {
        $roleOverride = $options?->getRoleOverride();
        if ($roleOverride === null && $request instanceof AbstractRequest) {
            $roleOverride = $request->getRoleOverride();
        }
        if ($roleOverride !== null) {
            $role = $roleOverride;
        }

        $resolvedTraceId = $this->requestPreparer->resolveTraceId($request, $traceId, $pipelineTraceId, $options);
        $context = new PipelineContext(
            request: $request,
            config: $this->config,
            traceId: $resolvedTraceId,
            role: $role,
            parent: $parent,
            options: $options,
            paginationOptions: $paginationOptions,
        );

        if ($request instanceof AbstractRequest) {
            $request->setContext($context);
        }

        return $context;
    }

    public function start(RequestInterface $request, PipelineContext $context, array &$audit): float
    {
        $startTime = microtime(true);
        $this->auditLogger->addAudit($audit, PipelineStage::Started, $context, null, null);
        $this->stageProcessor->process($request, $context, PipelineStage::Started);
        $this->auditLogger->log(LogLevel::INFO, 'Запрос начат', [
            'trace' => $context->traceId,
            'request' => $request::class,
            'role' => $context->role->value,
        ]);

        return $startTime;
    }
}
