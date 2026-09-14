<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Hydration;

use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;

final readonly class AmbiguousCardinalityDto
{
    /** @param AddressDto|list<AddressDto> $address */
    public function __construct(#[Nested(type: AddressDto::class)] public AddressDto|array $address)
    {
    }
}
