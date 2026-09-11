<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Casts;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final class IntegerCast implements CastInterface
{
    #[\Override]
    public function hydrate(mixed $value, ?PipelineContext $context = null): ?int
    {
        if ($value === null) {
            return null;
        }

        return (int) $value;
    }

    #[\Override]
    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        return $value === null ? null : (int) $value;
    }
}
