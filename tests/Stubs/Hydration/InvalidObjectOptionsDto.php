<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Hydration;

use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;

final readonly class InvalidObjectOptionsDto
{
    public function __construct(#[Nested(each: 'value')] public AddressDto $address)
    {
    }
}
