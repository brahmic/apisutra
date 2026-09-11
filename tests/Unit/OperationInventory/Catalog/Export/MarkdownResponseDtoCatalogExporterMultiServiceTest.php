<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Inventory\ServiceLabelResolverInterface;
use Brahmic\ApiSutra\OperationInventory\Catalog\Export\MarkdownResponseDtoCatalogExporter;
use Brahmic\ApiSutra\OperationInventory\Catalog\MultiServiceResponseDtoCatalogFactory;
use Brahmic\ApiSutra\OperationInventory\Catalog\ResponseDtoCatalog;
use Brahmic\ApiSutra\OperationInventory\OperationInventoryBuilder;
use Brahmic\ApiSutra\Request\RequestSpecResolver;
use Brahmic\ApiSutra\Resolver\ClassMapProvider;
use Brahmic\ApiSutra\Resolver\RequestScanner;
use Brahmic\ApiSutra\Tests\Stubs\CatalogMega\CatalogMegaClient;
use Brahmic\ApiSutra\Tests\Stubs\CatalogMega\ServiceA\MegaServiceAClient;
use Brahmic\ApiSutra\Tests\Stubs\CatalogMega\ServiceB\MegaServiceBClient;
use Brahmic\ApiSutra\Transport\MockTransport;

function ihpohMakeMultiServiceCatalog(): ResponseDtoCatalog
{
    $builder = new OperationInventoryBuilder(
        new RequestScanner(new ClassMapProvider()),
        new RequestSpecResolver(),
    );

    $serviceA = new MegaServiceAClient(
        new ClientConfig(baseUrl: 'https://api.test'),
        new MockTransport(),
        $builder->buildForRootNamespace('Brahmic\\ApiSutra\\Tests\\Stubs\\CatalogMega\\ServiceA'),
    );
    $serviceB = new MegaServiceBClient(
        new ClientConfig(baseUrl: 'https://api.test'),
        new MockTransport(),
        $builder->buildForRootNamespace('Brahmic\\ApiSutra\\Tests\\Stubs\\CatalogMega\\ServiceB'),
    );

    $mega = new CatalogMegaClient([$serviceA, $serviceB]);

    return (new MultiServiceResponseDtoCatalogFactory())->fromMultiService($mega);
}

function ihpohMakeSingleServiceCatalog(): ResponseDtoCatalog
{
    $builder = new OperationInventoryBuilder(
        new RequestScanner(new ClassMapProvider()),
        new RequestSpecResolver(),
    );
    $inventory = $builder->buildForRootNamespace('Brahmic\\ApiSutra\\Tests\\Stubs\\Catalog');

    return new ResponseDtoCatalog($inventory);
}

describe('MarkdownResponseDtoCatalogExporter в multi-service режиме', function () {
    it('добавляет секцию "## Сервисы" со счётчиками по сервисам', function () {
        $exporter = new MarkdownResponseDtoCatalogExporter();
        $markdown = $exporter->export(ihpohMakeMultiServiceCatalog());

        expect($markdown)->toContain('## Сервисы')
            ->and($markdown)->toContain('| Service | Sync | AsyncFinal | Download |')
            ->and($markdown)->toContain('MegaServiceAClient')
            ->and($markdown)->toContain('MegaServiceBClient');
    });

    it('добавляет колонку Service в by-resource секции', function () {
        $exporter = new MarkdownResponseDtoCatalogExporter();
        $markdown = $exporter->export(ihpohMakeMultiServiceCatalog());

        expect($markdown)->toContain('| Service | Request | HTTP | Endpoint | Kind | Response | Title |');
    });

    it('добавляет колонку Service в by-DTO секции', function () {
        $exporter = new MarkdownResponseDtoCatalogExporter();
        $markdown = $exporter->export(ihpohMakeMultiServiceCatalog());

        expect($markdown)->toContain('| Service | Request | Kind | Poll request | Unwrap |');
    });

    it('добавляет колонку Service в Download секции', function () {
        $exporter = new MarkdownResponseDtoCatalogExporter();
        $markdown = $exporter->export(ihpohMakeMultiServiceCatalog());

        expect($markdown)->toContain('| Service | Request | HTTP | Endpoint | Response | Resource |');
    });

    it('применяет custom ServiceLabelResolver', function () {
        $resolver = new class implements ServiceLabelResolverInterface {
            #[\Override]
            public function resolve(string $serviceClass): string
            {
                return str_replace(['MegaService', 'Client'], '', $this->shortName($serviceClass));
            }

            private function shortName(string $fqn): string
            {
                $position = strrpos($fqn, '\\');

                return $position === false ? $fqn : substr($fqn, $position + 1);
            }
        };

        $exporter = new MarkdownResponseDtoCatalogExporter($resolver);
        $markdown = $exporter->export(ihpohMakeMultiServiceCatalog());

        expect($markdown)->toContain('| A |')
            ->and($markdown)->toContain('| B |');
    });

    it('single-service catalog НЕ содержит секцию "## Сервисы" и НЕ имеет колонки Service', function () {
        $exporter = new MarkdownResponseDtoCatalogExporter();
        $markdown = $exporter->export(ihpohMakeSingleServiceCatalog());

        expect($markdown)->not->toContain('## Сервисы')
            ->and($markdown)->not->toContain('| Service | Request')
            ->and($markdown)->toContain('| Request | HTTP | Endpoint | Kind | Response | Title |');
    });
});
