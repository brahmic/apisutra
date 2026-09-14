<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Hydration;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;
use Brahmic\ApiSutra\Casts\IntegerCast;

final readonly class NestedCastPriorityDto
{
    public function __construct(
        #[From('main', fallback: ['alternative'])]
        #[Cast(IntegerCast::class)]
        #[Nested]
        public AddressDto $address,
    ) {
    }
}
