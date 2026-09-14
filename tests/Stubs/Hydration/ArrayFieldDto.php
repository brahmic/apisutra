<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Hydration;

final readonly class ArrayFieldDto
{
    /** @param array<string, mixed> $values */
    public function __construct(public array $values)
    {
    }
}
