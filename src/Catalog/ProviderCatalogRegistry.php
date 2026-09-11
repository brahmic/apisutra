<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Catalog;

use Brahmic\ApiSutra\Contracts\Interfaces\Catalog\ProviderCatalogInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Catalog\ProviderCatalogRegistryInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Catalog\RequestBoundProviderCatalogInterface;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;

final readonly class ProviderCatalogRegistry implements ProviderCatalogRegistryInterface
{
    /**
     * @var array<string, ProviderCatalogInterface>
     */
    private array $catalogs;

    /**
     * @param iterable<ProviderCatalogInterface> $catalogs
     */
    public function __construct(iterable $catalogs = [])
    {
        $indexed = [];

        foreach ($catalogs as $catalog) {
            $key = trim($catalog->key());
            if ($key === '') {
                throw new ConfigurationException('Ключ catalog не должен быть пустым');
            }

            if (array_key_exists($key, $indexed)) {
                throw new ConfigurationException('Catalog с ключом уже зарегистрирован: ' . $key);
            }

            $indexed[$key] = $catalog;
        }

        $this->catalogs = $indexed;
    }

    #[\Override]
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->catalogs);
    }

    #[\Override]
    public function get(string $key): ?ProviderCatalogInterface
    {
        return $this->catalogs[$key] ?? null;
    }

    /**
     * @return array<string, ProviderCatalogInterface>
     */
    #[\Override]
    public function all(): array
    {
        return $this->catalogs;
    }

    /**
     * @return array<string, RequestBoundProviderCatalogInterface>
     */
    #[\Override]
    public function forRequest(string $requestClass): array
    {
        $matched = [];

        foreach ($this->catalogs as $key => $catalog) {
            if (!$catalog instanceof RequestBoundProviderCatalogInterface) {
                continue;
            }

            if ($catalog->supportsRequest($requestClass)) {
                $matched[$key] = $catalog;
            }
        }

        return $matched;
    }
}
