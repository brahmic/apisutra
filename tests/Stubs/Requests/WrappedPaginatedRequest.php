<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Behavior\Pagination;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Pagination\AbstractPaginatedRequest;
use Brahmic\ApiSutra\Tests\Stubs\Dto\PaginationContainerDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\PaginationItemDto;
use Brahmic\ApiSutra\Tests\Stubs\Pagination\TestItemCollection;

#[Get('/wrapped')]
#[Returns(response: PaginationContainerDto::class)]
#[Pagination(
    itemsPath: 'response.result',
    itemsType: PaginationItemDto::class,
    itemsCollection: TestItemCollection::class,
)]
final class WrappedPaginatedRequest extends AbstractPaginatedRequest
{
    public function __construct(
        #[Query]
        public ?int $page = null,
        #[Query]
        public ?int $limit = null,
    ) {}
}
