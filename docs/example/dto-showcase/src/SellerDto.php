<?php

declare(strict_types=1);

namespace Example\DtoShowcase;

final readonly class SellerDto
{
    /** @param array<string, mixed> $_extra */
    public function __construct(
        public int $id,
        public string $name,
        public array $_extra = [],
    ) {
    }
}
