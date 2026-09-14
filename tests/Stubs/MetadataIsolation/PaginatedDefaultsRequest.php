<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\MetadataIsolation;

use Brahmic\ApiSutra\Attributes\Behavior\Pagination;
use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Pagination\AbstractPaginatedRequest;

#[Get('/page')]
#[Returns(DefaultsContainer::class)]
#[Pagination(itemsPath: 'data', itemsType: DefaultsDto::class)]
final class PaginatedDefaultsRequest extends AbstractPaginatedRequest
{
}
