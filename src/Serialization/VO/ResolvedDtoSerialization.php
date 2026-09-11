<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Serialization\VO;

use Brahmic\ApiSutra\Casts\CastRegistry;
use Brahmic\ApiSutra\Config\DtoSerializationPolicy;

final readonly class ResolvedDtoSerialization
{
    public function __construct(
        public DtoSerializationPolicy $policy,
        public CastRegistry $casts,
    ) {}
}
