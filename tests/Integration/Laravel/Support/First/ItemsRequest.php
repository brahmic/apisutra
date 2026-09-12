<?php

declare(strict_types=1);

namespace Integration\First;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/items')]
final class ItemsRequest extends AbstractRequest
{
    public function __construct(#[Query] public string $limit = '20')
    {
    }
}
