<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Profiles;

use Brahmic\ApiSutra\Config\DateTimeSerializationPolicy;
use Brahmic\ApiSutra\Config\DtoSerializationPolicy;
use Brahmic\ApiSutra\Contracts\Interfaces\Serialization\DtoSerializationProfileInterface;

final readonly class DateTimeSerializationTestProfile implements DtoSerializationProfileInterface
{
    #[\Override]
    public function policy(): DtoSerializationPolicy
    {
        return new DtoSerializationPolicy(
            dateTime: new DateTimeSerializationPolicy(
                format: 'Y-m-d H:i',
                timezone: 'UTC',
            ),
        );
    }

    public function casts(): array
    {
        return [];
    }
}
