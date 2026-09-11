<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Profiles;

use Brahmic\ApiSutra\Config\DtoSerializationPolicy;
use Brahmic\ApiSutra\Contracts\Interfaces\Serialization\DtoSerializationProfileInterface;
use Brahmic\ApiSutra\Tests\Stubs\Casts\UppercaseCast;

final readonly class UppercaseSerializationProfile implements DtoSerializationProfileInterface
{
    #[\Override]
    public function policy(): DtoSerializationPolicy
    {
        return new DtoSerializationPolicy();
    }

    public function casts(): array
    {
        return [
            'string' => new UppercaseCast(),
        ];
    }
}
