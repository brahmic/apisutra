<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Inventory\ResponseDtoCatalogProviderInterface;
use Brahmic\ApiSutra\OperationInventory\Catalog\ResponseDtoCatalog;
use Brahmic\ApiSutra\OperationInventory\OperationInventoryBuilder;
use Brahmic\ApiSutra\Request\RequestSpecResolver;
use Brahmic\ApiSutra\Resolver\ClassMapProvider;
use Brahmic\ApiSutra\Resolver\RequestScanner;
use Brahmic\ApiSutra\Tests\Stubs\CatalogMega\CatalogMegaClient;
use Brahmic\ApiSutra\Tests\Stubs\CatalogMega\ServiceA\MegaServiceAClient;
use Brahmic\ApiSutra\Tests\Stubs\CatalogMega\ServiceB\MegaServiceBClient;
use Brahmic\ApiSutra\Tests\Stubs\Inventory\InventoryCallPathClient;
use Brahmic\ApiSutra\Transport\MockTransport;

describe('ResponseDtoCatalogProviderInterface', function () {
    it('AbstractClient имплементирует контракт', function () {
        $client = new InventoryCallPathClient(
            new ClientConfig(baseUrl: 'https://api.test'),
            new MockTransport(),
        );

        expect($client)->toBeInstanceOf(ResponseDtoCatalogProviderInterface::class)
            ->and($client->responseDtoCatalog())->toBeInstanceOf(ResponseDtoCatalog::class);
    });

    it('мегаклиент через trait тоже имплементирует контракт', function () {
        $mega = new CatalogMegaClient([]);

        expect($mega)->toBeInstanceOf(ResponseDtoCatalogProviderInterface::class)
            ->and($mega->responseDtoCatalog())->toBeInstanceOf(ResponseDtoCatalog::class);
    });

    it('унифицированный полиморфный цикл по разным провайдерам', function () {
        $builder = new OperationInventoryBuilder(
            new RequestScanner(new ClassMapProvider()),
            new RequestSpecResolver(),
        );
        $config = new ClientConfig(baseUrl: 'https://api.test');
        $transport = new MockTransport();

        $standalone = new MegaServiceAClient(
            $config,
            $transport,
            $builder->buildForRootNamespace('Brahmic\\ApiSutra\\Tests\\Stubs\\CatalogMega\\ServiceA'),
        );
        $mega = new CatalogMegaClient([
            new MegaServiceBClient(
                $config,
                $transport,
                $builder->buildForRootNamespace('Brahmic\\ApiSutra\\Tests\\Stubs\\CatalogMega\\ServiceB'),
            ),
        ]);

        /** @var array<int, ResponseDtoCatalogProviderInterface> $providers */
        $providers = [$standalone, $mega];

        $catalogs = [];
        foreach ($providers as $provider) {
            $catalogs[] = $provider->responseDtoCatalog();
        }

        expect($catalogs)->toHaveCount(2)
            ->and($catalogs[0])->toBeInstanceOf(ResponseDtoCatalog::class)
            ->and($catalogs[1])->toBeInstanceOf(ResponseDtoCatalog::class);
    });
});
