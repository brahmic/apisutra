<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\DtoHydrationProfile;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;
use Brahmic\ApiSutra\Tests\Stubs\Profiles\UppercaseHydrationProfile;

#[DtoHydrationProfile(UppercaseHydrationProfile::class)]
final readonly class CastStringDto extends AbstractDto
{
    public function __construct(
        public string $value,
    ) {}
}
