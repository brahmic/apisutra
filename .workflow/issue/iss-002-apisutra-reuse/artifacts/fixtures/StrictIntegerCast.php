<?php

declare(strict_types=1);

namespace MaxSutraAudit;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use Override;

final readonly class StrictIntegerCast implements CastInterface
{
    #[Override]
    public function hydrate(mixed $value, ?PipelineContext $context = null): int
    {
        if (! is_int($value)) {
            throw HydrationException::invalidValue('strict_integer_expected', 'int', get_debug_type($value));
        }

        return $value;
    }

    #[Override]
    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        return $value;
    }
}
