<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Catalog;

interface RequestBoundProviderCatalogInterface extends ProviderCatalogInterface
{
    public function supportsRequest(string $requestClass): bool;
}
