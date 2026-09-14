<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Hydration;

use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;

final readonly class AmbiguousObjectDto
{
    public function __construct(#[Nested] public AddressDto|ArrayFieldDto $address)
    {
    }
}
