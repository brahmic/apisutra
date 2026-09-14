<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;

final readonly class StrictListDto
{
    /** @param list<PlainScalarDto> $items */
    public function __construct(#[Cast(StrictListCast::class, PlainScalarDto::class)] public array $items)
    {
    }
}
