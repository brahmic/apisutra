<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Behavior\Pagination;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Pagination\AbstractPaginatedRequest;

#[Get('/offset-paginated')]
#[Pagination(pageParam: 'offset', limitParam: 'limit', offsetBased: true)]
final class OffsetPaginatedRequest extends AbstractPaginatedRequest
{
    public function __construct(
        #[Query]
        public ?int $offset = null,
        #[Query]
        public ?int $limit = null,
    ) {}
}
