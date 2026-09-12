<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;
use Brahmic\ApiSutra\Casts\JsonCast;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;

final readonly class HydrationNestedJsonDto extends AbstractDto
{
    /** @param list<mixed> $items */
    public function __construct(#[Nested(each: 'value', itemCast: JsonCast::class)] public array $items) {}
}
