<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Serialization\VO;

use Brahmic\ApiSutra\Casts\CastRegistry;
use Brahmic\ApiSutra\Config\DtoHydrationPolicy;

final readonly class ResolvedDtoHydration
{
    public function __construct(
        public DtoHydrationPolicy $policy,
        public CastRegistry $casts,
    ) {
    }
}
