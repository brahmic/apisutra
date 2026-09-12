<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;

final readonly class IdentifierEnvelopeDto extends AbstractDto
{
    /** @param list<SimpleResponseDto> $items */
    public function __construct(
        public ?SimpleResponseDto $item = null,
        #[Nested(type: SimpleResponseDto::class)] public array $items = [],
    ) {}
}
