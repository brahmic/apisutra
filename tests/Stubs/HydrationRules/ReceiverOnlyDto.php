<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\HydrationRules;

final readonly class ReceiverOnlyDto
{
    public function __construct(public array $extra)
    {
    }
}
