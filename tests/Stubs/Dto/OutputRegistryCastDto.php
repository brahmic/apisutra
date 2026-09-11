<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\DtoSerializationProfile;
use Brahmic\ApiSutra\Attributes\DataTransfer\To;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;
use Brahmic\ApiSutra\Tests\Stubs\Profiles\UppercaseSerializationProfile;

#[DtoSerializationProfile(UppercaseSerializationProfile::class)]
final readonly class OutputRegistryCastDto extends AbstractDto
{
    public function __construct(
        #[To('code')]
        public string $code,
    ) {}
}
