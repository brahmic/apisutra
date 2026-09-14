<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\HydrationRules;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\ScopedCastInterface;
use Brahmic\ApiSutra\Serialization\Rules\HydrationScope;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class ScopedRowCast implements ScopedCastInterface
{
    public function hydrate(mixed $value, ?PipelineContext $context = null): mixed
    {
        return RowHydrationHelper::hydrate($value, null);
    }

    public function hydrateInScope(mixed $value, HydrationScope $scope): mixed
    {
        return RowHydrationHelper::hydrate($value, $scope);
    }

    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        return $value;
    }
}
