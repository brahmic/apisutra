<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;

final readonly class RequiredTypedDto
{
    public function __construct(#[Nested(type: PlainScalarDto::class)] public TypedItems $items)
    {
    }
}
