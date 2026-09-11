<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Serialization;

use Brahmic\ApiSutra\Config\DtoSerializationPolicy;
use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;

interface DtoSerializationProfileInterface
{
    public function policy(): DtoSerializationPolicy;

    /**
     * @return array<string, CastInterface|class-string<CastInterface>>
     */
    public function casts(): array;
}
