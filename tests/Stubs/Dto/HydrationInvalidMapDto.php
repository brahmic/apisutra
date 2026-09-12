<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;

final readonly class HydrationInvalidMapDto extends AbstractDto
{
    /** @param list<mixed> $items */
    public function __construct(#[Nested(discriminator: 'kind', map: ['known' => 'FixtureMissingDto'])] public array $items) {}
}
