<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;

final readonly class CombinedListDto
{
    /** @param list<PlainScalarDto> $items */
    public function __construct(
        #[Cast(StrictListCast::class)]
        #[Nested(type: PlainScalarDto::class)]
        public array $items,
    ) {
    }
}
