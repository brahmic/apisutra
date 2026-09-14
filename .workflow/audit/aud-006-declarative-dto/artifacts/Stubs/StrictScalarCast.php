<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class StrictScalarCast implements CastInterface
{
    public function __construct(private string $type)
    {
    }

    public function hydrate(mixed $value, ?PipelineContext $context = null): mixed
    {
        if (get_debug_type($value) !== $this->type) {
            throw HydrationException::invalidValue('invalid_field_type', $this->type, get_debug_type($value));
        }

        return $value;
    }

    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        return $value;
    }
}
