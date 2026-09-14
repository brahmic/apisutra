<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Continuation;

use Brahmic\ApiSutra\Result\ExecutionResult;

final readonly class ContinuationOutcome
{
    public function __construct(
        public mixed $value,
        public mixed $payload,
        public ?string $path,
        public ExecutionResult $lastResult,
        public int $attempts,
    ) {
    }
}
