<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Inventory\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Path;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/inventory/orders/{orderId}')]
final class InventoryOrderDetailsRequest extends AbstractRequest
{
    public function __construct(
        #[Path('orderId')]
        public string $orderId,
    ) {}
}
