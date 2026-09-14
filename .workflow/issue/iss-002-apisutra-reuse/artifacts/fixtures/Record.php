<?php

declare(strict_types=1);

namespace MaxSutraAudit;

final readonly class Record
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public int $id,
        public bool $active,
        public string $title,
        public ?string $label,
        public ?int $count = null,
        public array $extra = [],
    ) {}
}
