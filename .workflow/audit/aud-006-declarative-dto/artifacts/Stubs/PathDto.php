<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Attributes\DataTransfer\From;

final readonly class PathDto
{
    public function __construct(
        #[From('profile.batch_id', fallback: ['legacy.batch_id'])] public int $batchId,
    ) {
    }
}
