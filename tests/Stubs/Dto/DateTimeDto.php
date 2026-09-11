<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\Attributes\DataTransfer\DtoHydrationProfile;
use Brahmic\ApiSutra\Attributes\DataTransfer\DtoSerializationProfile;
use Brahmic\ApiSutra\Attributes\DataTransfer\To;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;
use Brahmic\ApiSutra\Tests\Stubs\Profiles\DateTimeHydrationTestProfile;
use Brahmic\ApiSutra\Tests\Stubs\Profiles\DateTimeSerializationTestProfile;
use DateTimeImmutable;

#[DtoHydrationProfile(DateTimeHydrationTestProfile::class)]
#[DtoSerializationProfile(DateTimeSerializationTestProfile::class)]
final readonly class DateTimeDto extends AbstractDto
{
    public function __construct(
        #[From('created_at')]
        #[To('created_at')]
        public ?DateTimeImmutable $createdAt = null,
    ) {}
}
