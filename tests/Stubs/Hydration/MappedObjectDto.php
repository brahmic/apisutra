<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Hydration;

use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;

final readonly class MappedObjectDto
{
    public function __construct(
        #[From('unused')]
        #[Nested(from: 'profile.address', fallback: ['legacy.address'])]
        public ?AddressDto $address = null,
    ) {
    }
}
