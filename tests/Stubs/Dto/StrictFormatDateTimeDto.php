<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\DtoHydrate;
use Brahmic\ApiSutra\Attributes\DataTransfer\DtoHydrationProfile;
use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;
use Brahmic\ApiSutra\Tests\Stubs\Profiles\DateTimeHydrationTestProfile;
use DateTimeImmutable;

#[DtoHydrationProfile(DateTimeHydrationTestProfile::class)]
#[DtoHydrate(
    dateTimeFormat: 'Y-m-d',
    dateTimeStrictFormat: true,
)]
final readonly class StrictFormatDateTimeDto extends AbstractDto
{
    public function __construct(
        #[From('created_at')]
        public DateTimeImmutable $createdAt,
    ) {}
}
