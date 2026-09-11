<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Pagination;

use Brahmic\ApiSutra\Contracts\Interfaces\Pagination\PaginationItemsCollectionFactoryInterface;

final class TestItemCollectionFactory implements PaginationItemsCollectionFactoryInterface
{
    public static bool $called = false;

    public static function reset(): void
    {
        self::$called = false;
    }

    public function make(array $items): array|object
    {
        self::$called = true;

        return new TestItemCollection($items);
    }
}
