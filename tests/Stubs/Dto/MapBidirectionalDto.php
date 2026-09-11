<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\DtoHydrationProfile;
use Brahmic\ApiSutra\Attributes\DataTransfer\Map;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;
use Brahmic\ApiSutra\Tests\Stubs\Profiles\SnakeCaseHydrationProfile;

#[DtoHydrationProfile(SnakeCaseHydrationProfile::class)]
final readonly class MapBidirectionalDto extends AbstractDto
{
    public function __construct(
        #[Map('query_num')]
        public string $queryNumber,
        public string $plainValue,
    ) {}
}
