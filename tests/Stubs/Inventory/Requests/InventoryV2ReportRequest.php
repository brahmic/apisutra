<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Inventory\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Path;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/inventory/v2/reports/{reportId}')]
final class InventoryV2ReportRequest extends AbstractRequest
{
    public function __construct(
        #[Path('reportId')]
        public string $reportId,
    ) {}
}
