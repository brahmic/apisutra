<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

final readonly class PlainGraphDto
{
    public function __construct(public PlainScalarDto $child)
    {
    }
}
