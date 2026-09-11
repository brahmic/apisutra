<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Casts;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final class UppercaseCast implements CastInterface
{
    public function hydrate(mixed $value, ?PipelineContext $context = null): mixed
    {
        return is_string($value) ? strtoupper($value) : $value;
    }

    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        return is_string($value) ? strtoupper($value) : $value;
    }
}
