<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Serialization;

use Brahmic\ApiSutra\Config\DtoHydrationPolicy;
use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;

interface DtoHydrationProfileInterface
{
    public function policy(): DtoHydrationPolicy;

    /**
     * @return array<string, CastInterface|class-string<CastInterface>>
     */
    public function casts(): array;
}
