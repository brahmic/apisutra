<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\HydrationRules;

use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Serialization\Rules\HydrationScope;

final readonly class RowHydrationHelper
{
    /**
     * @param list<array<string, mixed>> $items
     * @return list<object>
     */
    public static function hydrate(array $items, ?HydrationScope $scope): array
    {
        return $scope === null
            ? Hydrator::default()->hydrateCollection($items, RecordDto::class)
            : $scope->hydrateCollection($items, RecordDto::class);
    }
}
