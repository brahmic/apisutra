<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Catalog;

interface ProviderCatalogInterface
{
    public function key(): string;

    public function meta(): ProviderCatalogMetaInterface;
}
