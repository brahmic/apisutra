<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Execution\Batch;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use Brahmic\ApiSutra\Enums\Execution\ExecutionMode;
use Brahmic\ApiSutra\Enums\Execution\FailStrategy;
use Brahmic\ApiSutra\Enums\Execution\RequestRole;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

/**
 * Контекст выполнения batch.
 */
readonly class BatchContext
{
    public function __construct(
        public ?ClientInterface $client,
        public ExecutionMode $mode,
        public FailStrategy $failStrategy,
        public int $concurrency,
        public ?PipelineContext $parent,
        public RequestRole $role,
    ) {
    }
}
