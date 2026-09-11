<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\ProviderB\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Behavior\Pagination;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Pagination\AbstractPaginatedRequest;

#[Get('/provider-b/search')]
#[Pagination(pageParam: 'page', limitParam: 'limit')]
final class ProviderBSearchRequest extends AbstractPaginatedRequest
{
    public function __construct(
        #[Query]
        public string $query,
        #[Query]
        public ?int $page = null,
        #[Query]
        public ?int $limit = null,
    ) {}
}
