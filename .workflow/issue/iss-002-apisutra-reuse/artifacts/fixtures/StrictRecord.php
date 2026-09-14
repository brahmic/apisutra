<?php

declare(strict_types=1);

namespace MaxSutraAudit;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;

final readonly class StrictRecord
{
    public function __construct(
        #[Cast(StrictIntegerCast::class)] public int $id,
        #[Cast(StrictIntegerCast::class)] public ?int $count = null,
    ) {}
}
