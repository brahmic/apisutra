<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Behavior\Pagination;
use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Pagination\AbstractPaginatedRequest;
use Brahmic\ApiSutra\Tests\Stubs\Dto\PaginationContainerDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\PaginationItemDto;

#[Get('/wrapped')]
#[Returns(PaginationContainerDto::class, unwrap: 'response')]
#[Pagination(itemsPath: 'response.result', itemsType: PaginationItemDto::class)]
final class UnwrapPaginationRequest extends AbstractPaginatedRequest
{
}
