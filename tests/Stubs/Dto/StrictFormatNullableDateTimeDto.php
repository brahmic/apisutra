<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\DtoHydrate;
use Brahmic\ApiSutra\Attributes\DataTransfer\DtoHydrationProfile;
use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;
use Brahmic\ApiSutra\Enums\Serialization\DateTimeInvalidBehavior;
use Brahmic\ApiSutra\Tests\Stubs\Profiles\DateTimeHydrationTestProfile;
use DateTimeImmutable;

#[DtoHydrationProfile(DateTimeHydrationTestProfile::class)]
#[DtoHydrate(
    dateTimeFormat: 'Y-m-d',
    dateTimeStrictFormat: true,
    dateTimeInvalidBehavior: DateTimeInvalidBehavior::Null,
)]
final readonly class StrictFormatNullableDateTimeDto extends AbstractDto
{
    public function __construct(
        #[From('created_at')]
        public ?DateTimeImmutable $createdAt = null,
    ) {}
}
