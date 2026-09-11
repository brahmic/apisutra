<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\CatalogMega\ServiceB\Resources\Tax\Requests\PollTax;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/mega/serviceB/tax/poll')]
final class MegaPollTaxRequest extends AbstractRequest
{
    public function __construct(
        #[Query]
        public string $taskId,
    ) {}
}
