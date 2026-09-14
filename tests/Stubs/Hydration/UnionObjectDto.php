<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Hydration;

use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;

final readonly class UnionObjectDto
{
    public function __construct(#[Nested(type: AddressDto::class)] public AddressDto|ArrayFieldDto $address)
    {
    }
}
