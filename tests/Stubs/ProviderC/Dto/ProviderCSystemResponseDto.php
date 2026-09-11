<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\ProviderC\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\Casts\EnumCast;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;
use Brahmic\ApiSutra\Tests\Stubs\ProviderC\Enums\ProviderCSystemStatus;

final readonly class ProviderCSystemResponseDto extends AbstractDto
{
    public function __construct(
        #[From('status')]
        #[Cast(EnumCast::class, ProviderCSystemStatus::class)]
        public ProviderCSystemStatus $status,
        #[From('query_type')]
        public int $queryType,
        #[From('uuid')]
        public string $uuid,
    ) {}
}
