<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\DtoHydrationProfile;
use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;
use Brahmic\ApiSutra\Tests\Stubs\Profiles\EmptyStringNullHydrationProfile;

#[DtoHydrationProfile(EmptyStringNullHydrationProfile::class)]
final readonly class EmptyStringProfileNullableDto extends AbstractDto
{
    public function __construct(
        #[From('name')]
        public ?string $name = null,
    ) {}
}
