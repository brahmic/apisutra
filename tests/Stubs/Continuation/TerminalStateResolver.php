<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Continuation;

use Brahmic\ApiSutra\Continuation\ContinuationContext;
use Brahmic\ApiSutra\Continuation\ContinuationState;
use Brahmic\ApiSutra\Contracts\Interfaces\Continuation\ContinuationStateResolverInterface;
use Brahmic\ApiSutra\Result\ExecutionResult;

final readonly class TerminalStateResolver implements ContinuationStateResolverInterface
{
    public function resolve(ExecutionResult $result, ContinuationContext $context): ContinuationState
    {
        return ContinuationState::failed();
    }
}
