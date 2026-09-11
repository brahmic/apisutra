<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\ProviderB\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;

final readonly class ProviderBEventsResponseDto extends AbstractDto
{
    /**
     * @param array<int, array<string, mixed>> $events
     */
    public function __construct(
        #[From('events')]
        public array $events = [],
    ) {}
}
