<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\HydrationRules;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Behavior\Pagination;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Pagination\AbstractPaginatedRequest;
use Brahmic\ApiSutra\Tests\Stubs\Dto\PaginationContainerDto;

#[Get('/items')]
#[Returns(PaginationContainerDto::class)]
#[Pagination(itemsPath: 'response.rows', itemsType: RecordDto::class)]
final class ContainerRequest extends AbstractPaginatedRequest
{
}
