<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\MetadataIsolation;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class CountingCast implements CastInterface
{
    public function __construct(private MutableCounter $counter)
    {
    }

    public function hydrate(mixed $value, ?PipelineContext $context = null): mixed
    {
        return ++$this->counter->value;
    }

    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        return ++$this->counter->value;
    }
}
