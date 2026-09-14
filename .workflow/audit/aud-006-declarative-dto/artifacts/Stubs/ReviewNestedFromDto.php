<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;

final readonly class ReviewNestedFromDto
{
    public function __construct(#[Nested(from: 'source')] public PlainScalarDto $child)
    {
    }
}
