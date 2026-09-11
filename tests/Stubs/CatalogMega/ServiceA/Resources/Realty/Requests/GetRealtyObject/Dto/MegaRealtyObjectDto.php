<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\CatalogMega\ServiceA\Resources\Realty\Requests\GetRealtyObject\Dto;

final readonly class MegaRealtyObjectDto
{
    public function __construct(
        public string $id,
        public string $address,
    ) {}
}
