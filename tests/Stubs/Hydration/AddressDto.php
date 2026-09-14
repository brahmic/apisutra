<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Hydration;

use Brahmic\ApiSutra\Tests\Support\HydrationConstructionProbe;

final readonly class AddressDto
{
    public function __construct(public string $city)
    {
        HydrationConstructionProbe::$calls++;
    }
}
