<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Casts;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use UnitEnum;

final class EnumToStringCast implements CastInterface
{
    public function hydrate(mixed $value, ?PipelineContext $context = null): mixed
    {
        return $value;
    }

    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        if ($value instanceof UnitEnum) {
            return 'casted:' . $value->name;
        }

        return $value;
    }
}
