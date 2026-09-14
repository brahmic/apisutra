<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;

final readonly class MixedGraphDto extends StrictBase
{
    /** @param list<PlainScalarDto> $items */
    public function __construct(#[Nested(type: PlainScalarDto::class)] public array $items)
    {
    }
}
