<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Inventory\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/inventory/status')]
final class InventoryStatusRequest extends AbstractRequest
{
}
