<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\ConstructorOwned;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use Brahmic\ApiSutra\Enums\DataTransfer\ValueState;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class ValueHandler implements CastInterface, DefaultValueProviderInterface
{
    public function hydrate(mixed $value, ?PipelineContext $context = null): mixed
    {
        State::$handlers++;
        return $value;
    }

    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        return $value;
    }

    public function resolve(mixed $value, ValueState $state, array $source, ?PipelineContext $context): mixed
    {
        State::$handlers++;
        return State::$value;
    }
}
