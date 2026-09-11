<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Pagination;

/**
 * Контейнер для items в paginated-ответах.
 * Нужен, чтобы пагинатор мог извлечь items из DTO-обёртки.
 */
interface PaginationItemsContainerInterface
{
    /**
     * @return array<int, mixed>|object
     */
    public function items(): array|object;

    /**
     * @param array<int, mixed>|object $items
     */
    public function withItems(array|object $items): static;
}
