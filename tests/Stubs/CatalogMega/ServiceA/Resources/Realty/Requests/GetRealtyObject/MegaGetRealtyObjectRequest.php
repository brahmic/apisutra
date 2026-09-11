<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\CatalogMega\ServiceA\Resources\Realty\Requests\GetRealtyObject;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\OperationDescriptor;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\CatalogMega\ServiceA\Resources\Realty\Requests\GetRealtyObject\Dto\MegaRealtyObjectDto;

#[Get('/mega/serviceA/realty/object')]
#[Returns(MegaRealtyObjectDto::class)]
#[OperationDescriptor(title: 'ServiceA: Realty object')]
final class MegaGetRealtyObjectRequest extends AbstractRequest
{
    public function __construct(
        #[Query]
        public string $id,
    ) {}
}
