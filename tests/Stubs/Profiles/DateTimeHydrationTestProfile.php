<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Profiles;

use Brahmic\ApiSutra\Config\DateTimeHydrationPolicy;
use Brahmic\ApiSutra\Config\DtoHydrationPolicy;
use Brahmic\ApiSutra\Contracts\Interfaces\Serialization\DtoHydrationProfileInterface;

final readonly class DateTimeHydrationTestProfile implements DtoHydrationProfileInterface
{
    #[\Override]
    public function policy(): DtoHydrationPolicy
    {
        return new DtoHydrationPolicy(
            dateTime: new DateTimeHydrationPolicy(
                defaultTimezone: 'Europe/Moscow',
                preserveOffset: true,
            ),
        );
    }

    public function casts(): array
    {
        return [];
    }
}
