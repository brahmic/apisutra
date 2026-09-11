<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Casts;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final class BooleanCast implements CastInterface
{
    #[\Override]
    public function hydrate(mixed $value, ?PipelineContext $context = null): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) ((int) $value);
        }

        $normalized = strtolower((string) $value);
        return in_array($normalized, ['1', 'true', 'yes', 'y'], true);
    }

    #[\Override]
    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        return $value === null ? null : (bool) $value;
    }
}
