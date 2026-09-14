<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Hydration;

final readonly class PlainValueDto
{
    public function __construct(public string $value)
    {
    }
}
