<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\CatalogMega;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\MultiServiceClientInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Inventory\ResponseDtoCatalogProviderInterface;
use Brahmic\ApiSutra\Traits\ProvidesMultiServiceResponseDtoCatalogTrait;

final class CatalogMegaClient implements MultiServiceClientInterface, ResponseDtoCatalogProviderInterface
{
    use ProvidesMultiServiceResponseDtoCatalogTrait;

    /**
     * @param array<int, ClientInterface> $services
     */
    public function __construct(
        private readonly array $services,
    ) {}

    /**
     * @return array<int, ClientInterface>
     */
    #[\Override]
    public function services(): array
    {
        return $this->services;
    }
}
