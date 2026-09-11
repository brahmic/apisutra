<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Casts;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final class FloatCast implements CastInterface
{
    #[\Override]
    public function hydrate(mixed $value, ?PipelineContext $context = null): ?float
    {
        if ($value === null) {
            return null;
        }

        return (float) $value;
    }

    #[\Override]
    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        return $value === null ? null : (float) $value;
    }
}
