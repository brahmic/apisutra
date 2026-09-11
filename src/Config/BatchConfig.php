<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Config;

use Brahmic\ApiSutra\Enums\Execution\ExecutionMode;
use Brahmic\ApiSutra\Enums\Execution\FailStrategy;

final readonly class BatchConfig
{
    public function __construct(
        public ExecutionMode $mode = ExecutionMode::Sequential,
        public FailStrategy $failStrategy = FailStrategy::FailAll,
        public int $concurrency = 5,
    ) {}
}
