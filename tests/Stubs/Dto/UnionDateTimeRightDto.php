<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\DtoSerializationProfile;
use Brahmic\ApiSutra\Attributes\DataTransfer\To;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;
use Brahmic\ApiSutra\Tests\Stubs\Profiles\DateTimeSerializationTestProfile;
use DateTimeImmutable;

#[DtoSerializationProfile(DateTimeSerializationTestProfile::class)]
final readonly class UnionDateTimeRightDto extends AbstractDto
{
    public function __construct(
        #[To('created_at')]
        public string|DateTimeImmutable $createdAt,
    ) {}
}
