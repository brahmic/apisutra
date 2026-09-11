<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Behavior\Pagination;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Pagination\AbstractPaginatedRequest;

#[Get('/paginated')]
#[Pagination(pageParam: 'page', limitParam: 'limit', cursorParam: 'cursor')]
final class PaginatedRequest extends AbstractPaginatedRequest
{
    public function __construct(
        #[Query]
        public ?int $page = null,
        #[Query]
        public ?int $limit = null,
        #[Query]
        public ?string $cursor = null,
    ) {}
}
