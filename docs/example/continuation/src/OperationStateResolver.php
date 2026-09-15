<?php

declare(strict_types=1);

namespace Example\Continuation;

use Brahmic\ApiSutra\Continuation\ContinuationContext;
use Brahmic\ApiSutra\Continuation\ContinuationState;
use Brahmic\ApiSutra\Contracts\Interfaces\Continuation\ContinuationStateResolverInterface;
use Brahmic\ApiSutra\Result\ExecutionResult;

final readonly class OperationStateResolver implements ContinuationStateResolverInterface
{
    public function resolve(ExecutionResult $result, ContinuationContext $context): ContinuationState
    {
        $data = $result->response?->json();

        return match (is_array($data) ? ($data['status'] ?? null) : null) {
            'done' => ContinuationState::ready($data['data'] ?? null, 'data'),
            'failed' => ContinuationState::failed(),
            default => ContinuationState::pending(),
        };
    }
}
