<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Hydration;

use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;
use Brahmic\ApiSutra\Tests\Stubs\Pagination\TestItemCollection;

final readonly class CustomCollectionsDto
{
    public function __construct(
        #[Nested(type: AddressDto::class)] public ArrayWrapper $constructor,
        #[Nested(type: AddressDto::class)] public FactoryWrapper $factory,
        #[Nested(type: AddressDto::class)] public TestItemCollection $iterator,
    ) {
    }
}
