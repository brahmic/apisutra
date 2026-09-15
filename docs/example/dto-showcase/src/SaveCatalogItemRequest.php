<?php

declare(strict_types=1);

namespace Example\DtoShowcase;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\BodyRoot;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Post('/catalog')]
final class SaveCatalogItemRequest extends AbstractRequest
{
    public function __construct(#[BodyRoot] public CatalogItemDto $item)
    {
    }
}
