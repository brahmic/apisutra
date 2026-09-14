<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\HydrationRules;

use Brahmic\ApiSutra\Attributes\DataTransfer\DtoHydrate;

#[DtoHydrate]
final readonly class HydratePolicyDto
{
    public function __construct(public int $id)
    {
    }
}
