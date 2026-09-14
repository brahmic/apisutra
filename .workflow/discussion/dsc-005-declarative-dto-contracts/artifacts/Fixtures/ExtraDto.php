<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Workflow\Dsc005\Fixtures;

final readonly class ExtraDto
{
    /** @param array<string, mixed> $extra */
    public function __construct(public int $id, public array $extra)
    {
    }
}
