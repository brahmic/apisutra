<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\ConstructorOwned;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Behavior\Pagination;
use Brahmic\ApiSutra\Pagination\AbstractPaginatedRequest;

#[Get('/items')]
#[Pagination(itemsPath: 'response.rows', itemsType: NodeDto::class)]
final class ItemsRequest extends AbstractPaginatedRequest
{
}
