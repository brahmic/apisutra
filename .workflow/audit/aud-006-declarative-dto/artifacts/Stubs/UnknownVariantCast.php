<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class UnknownVariantCast implements CastInterface
{
    public function hydrate(mixed $value, ?PipelineContext $context = null): mixed
    {
        if (!is_array($value)) {
            throw HydrationException::invalidValue('unexpected_response_shape', 'array', get_debug_type($value));
        }

        // Известный вариант остаётся штатному discriminator; фабрика оборачивает остальные.
        return ($value['type'] ?? null) === 'known' ? $value : new RawVariant($value);
    }

    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        return $value instanceof RawVariant ? $value->payload : $value;
    }
}
