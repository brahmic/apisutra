<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

final readonly class CountedDto
{
    public function __construct(public int $id)
    {
        ConstructorCounter::$calls++;
    }
}
