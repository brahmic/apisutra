<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Hydration;

use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;

final readonly class PropertyObjectDto
{
    #[Nested(type: AddressDto::class)]
    public AddressDto $address;
}
