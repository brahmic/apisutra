<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Exceptions\Continuation;

use Brahmic\ApiSutra\Exceptions\Core\SdkException;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Throwable;

final class ContinuationAwaitException extends SdkException
{
    public function __construct(
        string $message,
        public readonly string $reason,
        public readonly int $attempts,
        public readonly ExecutionResult $lastResult,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        $context = [
            'reason' => $this->reason,
            'attempts' => $this->attempts,
            'httpStatus' => $this->lastResult->response?->status,
            'traceId' => $this->lastResult->traceId,
        ];
        $previous = $this->getPrevious();
        if ($this->reason === 'final_hydration_failed' && $previous instanceof HydrationException) {
            $context['hydration'] = $previous->context();
        }

        return $context;
    }
}
