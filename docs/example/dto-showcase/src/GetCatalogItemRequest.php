<?php

declare(strict_types=1);

namespace Example\DtoShowcase;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/catalog/7')]
#[Returns(CatalogItemDto::class, unwrap: 'data')]
final class GetCatalogItemRequest extends AbstractRequest
{
}
