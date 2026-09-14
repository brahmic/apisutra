<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\HydrationRules;

use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\Attributes\DataTransfer\To;
use Brahmic\ApiSutra\Attributes\DataTransfer\DateTimeTo;
use DateTimeImmutable;

final readonly class OutputAttributesDto
{
    public function __construct(
        #[From('source')] public int $id,
        #[To('out')] public string $label,
        #[DateTimeTo] public DateTimeImmutable $date,
        #[ForeignAttribute] public string $other = 'default',
    ) {
    }
}
