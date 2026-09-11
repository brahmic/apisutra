<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Pagination;

/**
 * Фабрика коллекции для items.
 */
interface PaginationItemsCollectionFactoryInterface
{
    /**
     * @param array<int, mixed> $items
     */
    public function make(array $items): array|object;
}
