<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Continuation;

use Brahmic\ApiSutra\Continuation\ContinuationContext;
use Brahmic\ApiSutra\Continuation\ContinuationState;
use Brahmic\ApiSutra\Contracts\Interfaces\Continuation\ContinuationStateResolverInterface;
use Brahmic\ApiSutra\Result\ExecutionResult;

final readonly class RootValueStateResolver implements ContinuationStateResolverInterface
{
    public function resolve(ExecutionResult $result, ContinuationContext $context): ContinuationState
    {
        $payload = $result->response?->json();
        return is_array($payload) && array_key_exists('value', $payload)
            ? ContinuationState::ready($payload)
            : ContinuationState::pending();
    }
}
