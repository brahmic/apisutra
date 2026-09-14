<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;

final readonly class StrictGraphDto extends StrictBase
{
    /** @param list<StrictScalarDto> $items */
    public function __construct(
        public ?StrictScalarDto $child = null,
        #[Nested(type: StrictScalarDto::class)] public array $items = [],
    ) {
    }
}
