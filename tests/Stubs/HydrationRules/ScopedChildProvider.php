<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\HydrationRules;

use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\ScopedDefaultValueProviderInterface;
use Brahmic\ApiSutra\Enums\DataTransfer\ValueState;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Serialization\Rules\HydrationScope;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class ScopedChildProvider implements ScopedDefaultValueProviderInterface
{
    public function resolve(mixed $value, ValueState $state, array $source, ?PipelineContext $context): mixed
    {
        return Hydrator::default()->hydrate($value, RecordDto::class);
    }

    public function resolveInScope(mixed $value, ValueState $state, array $source, HydrationScope $scope): mixed
    {
        return $scope->hydrate($value, RecordDto::class);
    }
}
