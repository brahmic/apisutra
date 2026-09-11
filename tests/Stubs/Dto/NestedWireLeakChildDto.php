<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\DtoSerializationProfile;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;
use Brahmic\ApiSutra\Tests\Stubs\Enums\TestStatus;
use Brahmic\ApiSutra\Tests\Stubs\Profiles\FallbackTitleValueStringDtoSerializationProfile;

#[DtoSerializationProfile(FallbackTitleValueStringDtoSerializationProfile::class)]
final readonly class NestedWireLeakChildDto extends AbstractDto
{
    public function __construct(
        public TestStatus $country,
    ) {}
}
