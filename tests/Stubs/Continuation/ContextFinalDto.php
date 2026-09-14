<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Continuation;

use Brahmic\ApiSutra\Continuation\ContinuationContext;

final readonly class ContextFinalDto
{
    public function __construct(public ContinuationContext $context)
    {
    }
}
