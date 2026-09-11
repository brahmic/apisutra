<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Profiles;

use Brahmic\ApiSutra\Config\DtoSerializationPolicy;
use Brahmic\ApiSutra\Contracts\Interfaces\Serialization\DtoSerializationProfileInterface;
use Brahmic\ApiSutra\Enums\Configuration\NamingStrategy;
use Brahmic\ApiSutra\Enums\Serialization\EnumOutput;

final readonly class AlternateDtoSerializationProfile implements DtoSerializationProfileInterface
{
    public function policy(): DtoSerializationPolicy
    {
        return new DtoSerializationPolicy(
            enumOutput: EnumOutput::Object,
            strictEnums: false,
            namingStrategy: NamingStrategy::None,
            serializeNulls: false,
        );
    }

    public function casts(): array
    {
        return [];
    }
}
