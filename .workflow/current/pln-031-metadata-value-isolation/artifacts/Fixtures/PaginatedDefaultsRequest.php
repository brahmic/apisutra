<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Workflow\Pln031\Fixtures;

use Brahmic\ApiSutra\Attributes\Behavior\Pagination;
use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Pagination\AbstractPaginatedRequest;
use Brahmic\ApiSutra\Tests\Stubs\Dto\PaginationContainerDto;

#[Get('/page')]
#[Returns(PaginationContainerDto::class)]
#[Pagination(itemsPath: 'data', itemsType: DefaultsDto::class)]
final class PaginatedDefaultsRequest extends AbstractPaginatedRequest
{
}
