<?php

declare(strict_types=1);

namespace Example\Records\Resources\Records\Get;

final readonly class GetRecordResponseDto
{
    /** @param array<string, mixed> $_extra */
    public function __construct(
        public int $id,
        public string $title,
        public array $_extra = [],
    ) {
    }
}
