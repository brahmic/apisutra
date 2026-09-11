<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\CatalogMega\ServiceB\Resources\Tax\Requests\GetTaxInfo;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\OperationDescriptor;
use Brahmic\ApiSutra\Attributes\Response\ContinuationResult;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\CatalogMega\ServiceB\Resources\Tax\Requests\GetTaxInfo\Dto\MegaTaxInfoFinalDto;
use Brahmic\ApiSutra\Tests\Stubs\CatalogMega\ServiceB\Resources\Tax\Requests\GetTaxInfo\Dto\MegaTaxInfoStartDto;
use Brahmic\ApiSutra\Tests\Stubs\CatalogMega\ServiceB\Resources\Tax\Requests\PollTax\MegaPollTaxRequest;

#[Post('/mega/serviceB/tax/info')]
#[Returns(MegaTaxInfoStartDto::class)]
#[ContinuationResult(
    finalType: MegaTaxInfoFinalDto::class,
    pollRequest: MegaPollTaxRequest::class,
)]
#[OperationDescriptor(title: 'ServiceB: Tax info')]
final class MegaGetTaxInfoRequest extends AbstractRequest
{
}
