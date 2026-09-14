<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\HydrationRules;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\ScopedCastInterface;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Serialization\Rules\HydrationScope;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class ScopedChildCast implements ScopedCastInterface
{
    public function hydrate(mixed $value, ?PipelineContext $context = null): mixed
    {
        return Hydrator::default()->hydrate($value, RecordDto::class);
    }

    public function hydrateInScope(mixed $value, HydrationScope $scope): mixed
    {
        return $scope->hydrate($value, RecordDto::class);
    }

    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        return $value;
    }
}
