<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Casts;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\Serialization\JsonEncoder;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use JsonException;
use Override;

final class JsonCast implements CastInterface
{
    #[Override]
    public function hydrate(mixed $value, ?PipelineContext $context = null): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            try {
                return json_decode($value, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
            } catch (JsonException $exception) {
                throw HydrationException::invalidValue('invalid_json', 'valid JSON', 'string', previous: $exception);
            }
        }

        return $value;
    }

    #[Override]
    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        if ($value === null) {
            return null;
        }

        return JsonEncoder::encode($value);
    }
}
