<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Hydration;

use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\DtoInterface;
use Brahmic\ApiSutra\Tests\Stubs\Dto\NestedItemDto;

final readonly class InterfaceObjectDto
{
    public function __construct(#[Nested(type: NestedItemDto::class)] public DtoInterface $item)
    {
    }
}
