<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Inventory\ResponseDtoKind;
use Brahmic\ApiSutra\OperationInventory\Catalog\MultiServiceResponseDtoCatalogFactory;
use Brahmic\ApiSutra\OperationInventory\Catalog\ResponseDtoUsage;
use Brahmic\ApiSutra\OperationInventory\OperationInventoryBuilder;
use Brahmic\ApiSutra\Request\RequestSpecResolver;
use Brahmic\ApiSutra\Resolver\ClassMapProvider;
use Brahmic\ApiSutra\Resolver\RequestScanner;
use Brahmic\ApiSutra\Tests\Stubs\CatalogMega\CatalogMegaClient;
use Brahmic\ApiSutra\Tests\Stubs\CatalogMega\ServiceA\MegaServiceAClient;
use Brahmic\ApiSutra\Tests\Stubs\CatalogMega\ServiceA\Resources\Files\Requests\DownloadFile\MegaServiceADownloadFileRequest;
use Brahmic\ApiSutra\Tests\Stubs\CatalogMega\ServiceA\Resources\Realty\Requests\GetRealtyObject\Dto\MegaRealtyObjectDto;
use Brahmic\ApiSutra\Tests\Stubs\CatalogMega\ServiceA\Resources\Realty\Requests\GetRealtyObject\MegaGetRealtyObjectRequest;
use Brahmic\ApiSutra\Tests\Stubs\CatalogMega\ServiceB\MegaServiceBClient;
use Brahmic\ApiSutra\Tests\Stubs\CatalogMega\ServiceB\Resources\Tax\Requests\GetTaxInfo\Dto\MegaTaxInfoFinalDto;
use Brahmic\ApiSutra\Tests\Stubs\CatalogMega\ServiceB\Resources\Tax\Requests\GetTaxInfo\Dto\MegaTaxInfoStartDto;
use Brahmic\ApiSutra\Tests\Stubs\CatalogMega\ServiceB\Resources\Tax\Requests\GetTaxInfo\MegaGetTaxInfoRequest;
use Brahmic\ApiSutra\Tests\Stubs\CatalogMega\ServiceB\Resources\Tax\Requests\PollTax\MegaPollTaxRequest;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\VO\Files\FileResponse;

function ihpohMakeMegaClient(): CatalogMegaClient
{
    $builder = new OperationInventoryBuilder(
        new RequestScanner(new ClassMapProvider()),
        new RequestSpecResolver(),
    );

    $serviceAInventory = $builder->buildForRootNamespace(
        'Brahmic\\ApiSutra\\Tests\\Stubs\\CatalogMega\\ServiceA',
    );
    $serviceBInventory = $builder->buildForRootNamespace(
        'Brahmic\\ApiSutra\\Tests\\Stubs\\CatalogMega\\ServiceB',
    );

    $config = new ClientConfig(baseUrl: 'https://api.test');
    $transport = new MockTransport();

    $serviceA = new MegaServiceAClient($config, $transport, $serviceAInventory);
    $serviceB = new MegaServiceBClient($config, $transport, $serviceBInventory);

    return new CatalogMegaClient([$serviceA, $serviceB]);
}

describe('MultiServiceResponseDtoCatalogFactory', function () {
    it('собирает каталог по всему мегаклиенту через services()', function () {
        $catalog = (new MultiServiceResponseDtoCatalogFactory())->fromMultiService(ihpohMakeMegaClient());

        $sync = $catalog->listSyncDtoClasses();
        $async = $catalog->listAsyncFinalDtoClasses();

        expect($sync)->toContain(MegaRealtyObjectDto::class)
            ->and($sync)->toContain(MegaTaxInfoStartDto::class)
            ->and($async)->toContain(MegaTaxInfoFinalDto::class);
    });

    it('проставляет serviceClass на usage для каждого сервиса', function () {
        $catalog = (new MultiServiceResponseDtoCatalogFactory())->fromMultiService(ihpohMakeMegaClient());

        $realtyUsage = $catalog->usagesFor(MegaRealtyObjectDto::class)[0];
        $taxFinalUsage = $catalog->usagesFor(MegaTaxInfoFinalDto::class)[0];

        expect($realtyUsage->serviceClass)->toBe(MegaServiceAClient::class)
            ->and($taxFinalUsage->serviceClass)->toBe(MegaServiceBClient::class)
            ->and($taxFinalUsage->pollRequest)->toBe(MegaPollTaxRequest::class);
    });

    it('download usage помечается serviceClass и FileResponse', function () {
        $catalog = (new MultiServiceResponseDtoCatalogFactory())->fromMultiService(ihpohMakeMegaClient());

        $usages = $catalog->usagesFor(FileResponse::class);
        expect($usages)->toHaveCount(1);

        $usage = $usages[0];
        expect($usage->kind)->toBe(ResponseDtoKind::Download)
            ->and($usage->requestClass)->toBe(MegaServiceADownloadFileRequest::class)
            ->and($usage->serviceClass)->toBe(MegaServiceAClient::class);
    });

    it('каталог объединяет сервисы в один listAllDtoClasses', function () {
        $catalog = (new MultiServiceResponseDtoCatalogFactory())->fromMultiService(ihpohMakeMegaClient());

        $all = $catalog->listAllDtoClasses();

        expect($all)->toContain(MegaRealtyObjectDto::class)
            ->and($all)->toContain(MegaTaxInfoStartDto::class)
            ->and($all)->toContain(MegaTaxInfoFinalDto::class)
            ->and($all)->not->toContain(FileResponse::class);
    });

    it('пустой services() даёт пустой каталог', function () {
        $mega = new CatalogMegaClient([]);
        $catalog = (new MultiServiceResponseDtoCatalogFactory())->fromMultiService($mega);

        expect($catalog->listAllDtoClasses())->toBe([])
            ->and($catalog->usages())->toBe([]);
    });

    it('start request у async имеет sync usage с serviceClass из своего сервиса', function () {
        $catalog = (new MultiServiceResponseDtoCatalogFactory())->fromMultiService(ihpohMakeMegaClient());

        $startUsages = $catalog->usagesFor(MegaTaxInfoStartDto::class);
        expect($startUsages)->toHaveCount(1);

        $startUsage = $startUsages[0];

        expect($startUsage->kind)->toBe(ResponseDtoKind::Sync)
            ->and($startUsage->requestClass)->toBe(MegaGetTaxInfoRequest::class)
            ->and($startUsage->serviceClass)->toBe(MegaServiceBClient::class);
    });

    it('trait responseDtoCatalog кеширует каталог на инстансе', function () {
        $mega = ihpohMakeMegaClient();

        $first = $mega->responseDtoCatalog();
        $second = $mega->responseDtoCatalog();

        expect($first)->toBe($second);
    });
});
