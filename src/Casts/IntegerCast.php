<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Casts;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\Exceptions\Serialization\SerializationException;
use Brahmic\ApiSutra\Serialization\IntegerRange;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use Override;

final class IntegerCast implements CastInterface
{
    #[Override]
    public function hydrate(mixed $value, ?PipelineContext $context = null): ?int
    {
        if ($value === null) {
            return null;
        }

        if (IntegerRange::overflows($value)) {
            throw HydrationException::invalidValue('integer_out_of_range', 'int', get_debug_type($value));
        }

        return (int) $value;
    }

    #[Override]
    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        if (IntegerRange::overflows($value)) {
            throw new SerializationException('Число вне диапазона int при сериализации IntegerCast');
        }
        return $value === null ? null : (int) $value;
    }
}
