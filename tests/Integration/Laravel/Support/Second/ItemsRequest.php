<?php

declare(strict_types=1);

namespace Integration\Second;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/items')]
final class ItemsRequest extends AbstractRequest
{
}
