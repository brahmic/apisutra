<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Casts;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\Serialization\JsonEncoder;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
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
            return json_decode($value, true);
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
