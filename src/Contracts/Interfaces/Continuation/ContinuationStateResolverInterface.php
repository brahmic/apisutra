<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Continuation;

use Brahmic\ApiSutra\Continuation\ContinuationContext;
use Brahmic\ApiSutra\Continuation\ContinuationState;
use Brahmic\ApiSutra\Result\ExecutionResult;

interface ContinuationStateResolverInterface
{
    /** Определяет состояние протокола без I/O и создания финального DTO. */
    public function resolve(ExecutionResult $result, ContinuationContext $context): ContinuationState;
}
