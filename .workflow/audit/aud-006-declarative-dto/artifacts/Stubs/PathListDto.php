<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;

final readonly class PathListDto
{
    /** @param list<PathDto> $items */
    public function __construct(#[Nested(type: PathDto::class, from: 'records')] public array $items)
    {
    }
}
