<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Inventory\ResponseDtoKind;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\OperationInventory\Catalog\MultiServiceResponseDtoCatalogFactory;
use Brahmic\ApiSutra\OperationInventory\Catalog\ResponseDtoUsage;
use Brahmic\ApiSutra\OperationInventory\OperationInventoryBuilder;
use Brahmic\ApiSutra\Request\RequestSpecResolver;
use Brahmic\ApiSutra\Resolver\ClassMapProvider;
use Brahmic\ApiSutra\Resolver\RequestScanner;
use Brahmic\ApiSutra\Tests\Stubs\CatalogMega\CatalogMegaClient;
use Brahmic\ApiSutra\Tests\Stubs\CatalogMega\ServiceA\MegaServiceAClient;
use Brahmic\ApiSutra\Tests\Stubs\CatalogMega\ServiceA\Resources\Realty\Requests\GetRealtyObject\Dto\MegaRealtyObjectDto;
use Brahmic\ApiSutra\Tests\Stubs\CatalogMega\ServiceB\MegaServiceBClient;
use Brahmic\ApiSutra\Tests\Stubs\CatalogMega\ServiceB\Resources\Tax\Requests\GetTaxInfo\Dto\MegaTaxInfoFinalDto;
use Brahmic\ApiSutra\Tests\Stubs\CatalogMega\ServiceB\Resources\Tax\Requests\GetTaxInfo\Dto\MegaTaxInfoStartDto;
use Brahmic\ApiSutra\Transport\MockTransport;

function ihpohMakeProviders(): array
{
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

    return ['standalone' => $standalone, 'mega' => $mega];
}

describe('MultiServiceResponseDtoCatalogFactory::merge', function () {
    it('объединяет одиночный клиент и мегаклиент в один каталог', function () {
        ['standalone' => $standalone, 'mega' => $mega] = ihpohMakeProviders();

        $catalog = (new MultiServiceResponseDtoCatalogFactory())->merge($standalone, $mega);

        $sync = $catalog->listSyncDtoClasses();
        $async = $catalog->listAsyncFinalDtoClasses();

        expect($sync)->toContain(MegaRealtyObjectDto::class)
            ->and($sync)->toContain(MegaTaxInfoStartDto::class)
            ->and($async)->toContain(MegaTaxInfoFinalDto::class);
    });

    it('serviceClass для одиночного клиента = FQCN самого клиента', function () {
        ['standalone' => $standalone, 'mega' => $mega] = ihpohMakeProviders();

        $catalog = (new MultiServiceResponseDtoCatalogFactory())->merge($standalone, $mega);
        $usage = $catalog->usagesFor(MegaRealtyObjectDto::class)[0];

        expect($usage->serviceClass)->toBe(MegaServiceAClient::class);
    });

    it('serviceClass для services() мегаклиента = FQCN сервиса', function () {
        ['standalone' => $standalone, 'mega' => $mega] = ihpohMakeProviders();

        $catalog = (new MultiServiceResponseDtoCatalogFactory())->merge($standalone, $mega);
        $usage = $catalog->usagesFor(MegaTaxInfoFinalDto::class)[0];

        expect($usage->serviceClass)->toBe(MegaServiceBClient::class);
    });

    it('при пустом списке провайдеров каталог пустой', function () {
        $catalog = (new MultiServiceResponseDtoCatalogFactory())->merge();

        expect($catalog->listAllDtoClasses())->toBe([])
            ->and($catalog->usages())->toBe([]);
    });

    it('первый провайдер с дублирующимся requestClass выигрывает', function () {
        $builder = new OperationInventoryBuilder(
            new RequestScanner(new ClassMapProvider()),
            new RequestSpecResolver(),
        );
        $config = new ClientConfig(baseUrl: 'https://api.test');
        $transport = new MockTransport();

        $serviceA1 = new MegaServiceAClient(
            $config,
            $transport,
            $builder->buildForRootNamespace('Brahmic\\ApiSutra\\Tests\\Stubs\\CatalogMega\\ServiceA'),
        );
        $serviceA2 = new MegaServiceBClient(
            $config,
            $transport,
            $builder->buildForRootNamespace('Brahmic\\ApiSutra\\Tests\\Stubs\\CatalogMega\\ServiceA'),
        );

        $catalog = (new MultiServiceResponseDtoCatalogFactory())->merge($serviceA1, $serviceA2);
        $usage = $catalog->usagesFor(MegaRealtyObjectDto::class)[0];

        expect($usage->serviceClass)->toBe(MegaServiceAClient::class);
    });

    it('каталог содержит usage из обоих провайдеров', function () {
        ['standalone' => $standalone, 'mega' => $mega] = ihpohMakeProviders();

        $catalog = (new MultiServiceResponseDtoCatalogFactory())->merge($standalone, $mega);
        $allUsages = [];
        foreach ($catalog->usages() as $items) {
            foreach ($items as $item) {
                $allUsages[] = $item;
            }
        }

        $servicesSeen = array_values(array_unique(array_map(
            static fn (ResponseDtoUsage $u) => $u->serviceClass,
            $allUsages,
        )));
        sort($servicesSeen);

        $expected = [MegaServiceAClient::class, MegaServiceBClient::class];
        sort($expected);

        expect($servicesSeen)->toBe($expected);
    });

    it('download usage из одиночного клиента сохраняет kind и serviceClass', function () {
        ['standalone' => $standalone, 'mega' => $mega] = ihpohMakeProviders();

        $catalog = (new MultiServiceResponseDtoCatalogFactory())->merge($standalone, $mega);
        $downloads = $catalog->listDownloadResponseClasses();

        expect($downloads)->not->toBeEmpty();

        $usages = $catalog->usagesFor($downloads[0]);
        expect($usages[0]->kind)->toBe(ResponseDtoKind::Download)
            ->and($usages[0]->serviceClass)->toBe(MegaServiceAClient::class);
    });

    it('бросает ConfigurationException на провайдер не-ClientInterface и не-MultiService', function () {
        $exoticProvider = new class implements \Brahmic\ApiSutra\Contracts\Interfaces\Inventory\ResponseDtoCatalogProviderInterface {
            #[\Override]
            public function responseDtoCatalog(): \Brahmic\ApiSutra\OperationInventory\Catalog\ResponseDtoCatalog
            {
                return new \Brahmic\ApiSutra\OperationInventory\Catalog\ResponseDtoCatalog(
                    new \Brahmic\ApiSutra\OperationInventory\OperationInventory([]),
                );
            }
        };

        (new MultiServiceResponseDtoCatalogFactory())->merge($exoticProvider);
    })->throws(ConfigurationException::class);
});
