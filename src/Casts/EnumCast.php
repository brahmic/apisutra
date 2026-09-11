<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Casts;

use BackedEnum;
use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use UnitEnum;

final class EnumCast implements CastInterface
{
    public function __construct(
        private readonly ?string $enumClass = null,
    ) {}

    #[\Override]
    public function hydrate(mixed $value, ?PipelineContext $context = null): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($this->enumClass !== null && enum_exists($this->enumClass)) {
            $class = $this->enumClass;
            return $class::tryFrom($value);
        }

        return $value;
    }

    #[\Override]
    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        if ($value instanceof UnitEnum) {
            if ($value instanceof BackedEnum) {
                return $value->value;
            }

            return $value->name;
        }

        return $value;
    }
}
