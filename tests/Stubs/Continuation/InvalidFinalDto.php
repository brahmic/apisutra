<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Continuation;

final readonly class InvalidFinalDto
{
    public function __construct(public int $count)
    {
    }
}
