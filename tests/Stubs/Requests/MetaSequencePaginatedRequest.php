<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Behavior\Pagination;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Pagination\AbstractPaginatedRequest;
use Brahmic\ApiSutra\VO\Metadata\PaginationMeta;

#[Get('/meta-sequence')]
#[Pagination(pageParam: 'page', limitParam: 'limit')]
final class MetaSequencePaginatedRequest extends AbstractPaginatedRequest
{
    /**
     * @var array<int, PaginationMeta>
     */
    private static array $metaQueue = [];

    public static function setMetaQueue(array $queue): void
    {
        self::$metaQueue = $queue;
    }

    public static function reset(): void
    {
        self::$metaQueue = [];
    }

    public function __construct(
        #[Query]
        public ?int $page = null,
        #[Query]
        public ?int $limit = null,
    ) {}

    public function extractMeta(array $response): PaginationMeta
    {
        $next = array_shift(self::$metaQueue);
        if ($next instanceof PaginationMeta) {
            return $next;
        }

        return new PaginationMeta(
            total: null,
            currentPage: 1,
            perPage: 0,
            hasMore: false,
            nextCursor: null,
        );
    }
}
