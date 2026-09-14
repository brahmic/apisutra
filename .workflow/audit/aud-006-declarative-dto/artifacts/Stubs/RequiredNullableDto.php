<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

final readonly class RequiredNullableDto
{
    public function __construct(public ?int $count)
    {
    }
}
