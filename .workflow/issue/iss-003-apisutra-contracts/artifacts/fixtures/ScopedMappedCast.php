<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\ScopedCastInterface;
use Brahmic\ApiSutra\Serialization\Rules\HydrationScope;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class ScopedMappedCast implements ScopedCastInterface
{
    public function hydrateInScope(mixed $value, HydrationScope $scope): mixed
    {
        return $scope->hydrate($value, Mapped::class);
    }

    public function hydrate(mixed $value, ?PipelineContext $context = null): mixed
    {
        throw new LogicException('Expected scoped cast.');
    }

    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        return $value;
    }
}
