<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\HydrationRules;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final class CountingCast implements CastInterface
{
    private int $count = 0;

    public function hydrate(mixed $value, ?PipelineContext $context = null): mixed
    {
        return ++$this->count;
    }

    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        return ++$this->count;
    }
}
