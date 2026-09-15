<?php

declare(strict_types=1);

namespace Example\HydrationRules;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\ScopedCastInterface;
use Brahmic\ApiSutra\Serialization\Rules\HydrationScope;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use Brahmic\ApiSutra\Serialization\Hydrator;

final readonly class EntryCast implements ScopedCastInterface
{
    public function hydrateInScope(mixed $value, HydrationScope $scope): mixed
    {
        return $scope->hydrate($value, EntryDto::class);
    }

    public function hydrate(mixed $value, ?PipelineContext $context = null): mixed
    {
        return Hydrator::default()->hydrate($value, EntryDto::class);
    }

    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        return $value;
    }
}
