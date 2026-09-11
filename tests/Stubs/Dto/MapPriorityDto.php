<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\Attributes\DataTransfer\Map;
use Brahmic\ApiSutra\Attributes\DataTransfer\To;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;

final readonly class MapPriorityDto extends AbstractDto
{
    public function __construct(
        #[Map('shared_key')]
        #[From('from_key')]
        public string $fromPreferred,
        #[Map('shared_key')]
        #[To('to_key')]
        public string $toPreferred,
        #[Map('ownersheep_type')]
        public string $ownershipType,
    ) {}
}
