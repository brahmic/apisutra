<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Behavior\Pagination;
use Brahmic\ApiSutra\Pagination\AbstractPaginatedRequest;
use Brahmic\ApiSutra\VO\Metadata\PaginationMeta;

#[Get('/meta-override')]
#[Pagination(pageParam: 'page', limitParam: 'limit')]
final class CustomMetaPaginatedRequest extends AbstractPaginatedRequest
{
    public static bool $extractCalled = false;

    public static function reset(): void
    {
        self::$extractCalled = false;
    }

    public function extractMeta(array $response): PaginationMeta
    {
        self::$extractCalled = true;

        return new PaginationMeta(
            total: 0,
            currentPage: 1,
            perPage: 10,
            hasMore: false,
            nextCursor: null,
        );
    }
}
