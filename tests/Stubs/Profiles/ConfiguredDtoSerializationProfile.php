<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Profiles;

use Brahmic\ApiSutra\Config\DtoSerializationPolicy;
use Brahmic\ApiSutra\Contracts\Interfaces\Serialization\DtoSerializationProfileInterface;

final readonly class ConfiguredDtoSerializationProfile implements DtoSerializationProfileInterface
{
    public function __construct(
        private ?DtoSerializationPolicy $policy = null,
    ) {}

    public function policy(): DtoSerializationPolicy
    {
        return $this->policy ?? new DtoSerializationPolicy();
    }

    public function casts(): array
    {
        return [];
    }
}
