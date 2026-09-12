<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Workflow\HydrationProbe;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Casts\JsonCast;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;

final readonly class JsonPayloadDto extends AbstractDto
{
    public function __construct(#[Cast(JsonCast::class)] public mixed $payload) {}
}
