<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\HydrationRules;

final readonly class RecordDto
{
    public function __construct(public int $id)
    {
    }
}
