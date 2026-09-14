<?php

declare(strict_types=1);

namespace MaxSutraAudit;

use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;

final readonly class RecordList
{
    /** @param list<Record|array<string, mixed>> $items */
    public function __construct(
        #[From('batch_id')] public int $batchId,
        #[Nested(discriminator: 'type', map: ['known' => Record::class])] public array $items,
    ) {}
}
