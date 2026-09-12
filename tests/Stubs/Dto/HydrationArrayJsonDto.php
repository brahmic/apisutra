<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Casts\JsonCast;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;

final readonly class HydrationArrayJsonDto extends AbstractDto
{
    /** @param array<array-key, mixed> $payload */
    public function __construct(#[Cast(JsonCast::class)] public array $payload) {}
}
