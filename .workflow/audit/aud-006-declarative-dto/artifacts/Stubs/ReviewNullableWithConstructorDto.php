<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

final class ReviewNullableWithConstructorDto
{
    public ?int $count;

    public function __construct(public int $id = 0)
    {
    }
}
