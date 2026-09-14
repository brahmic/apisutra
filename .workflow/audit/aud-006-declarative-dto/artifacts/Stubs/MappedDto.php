<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Attributes\DataTransfer\From;

final readonly class MappedDto
{
    /** @param array<string, mixed> $extras */
    public function __construct(
        #[From('record_id')] public int $id,
        public bool $active = false,
        public array $extras = [],
    ) {
    }
}
