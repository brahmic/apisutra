<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Contracts\Interfaces\Inventory\ResponseDtoCatalogExporterInterface;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\OperationInventory\Catalog\Export\MarkdownResponseDtoCatalogExporter;
use Brahmic\ApiSutra\OperationInventory\Catalog\Export\ResponseDtoCatalogWriter;
use Brahmic\ApiSutra\OperationInventory\Catalog\ResponseDtoCatalog;
use Brahmic\ApiSutra\OperationInventory\OperationInventoryBuilder;
use Brahmic\ApiSutra\Request\RequestSpecResolver;
use Brahmic\ApiSutra\Resolver\ClassMapProvider;
use Brahmic\ApiSutra\Resolver\RequestScanner;

function apisutraBuildCatalogForWriterTest(): ResponseDtoCatalog
{
    $builder = new OperationInventoryBuilder(
        new RequestScanner(new ClassMapProvider()),
        new RequestSpecResolver(),
    );
    $inventory = $builder->buildForRootNamespace('Brahmic\\ApiSutra\\Tests\\Stubs\\Catalog');

    return new ResponseDtoCatalog($inventory);
}

describe('ResponseDtoCatalogWriter', function () {
    it('рендерит markdown через built-in exporter', function () {
        $writer = new ResponseDtoCatalogWriter([new MarkdownResponseDtoCatalogExporter()]);
        $markdown = $writer->render(apisutraBuildCatalogForWriterTest(), 'md');

        expect($markdown)->toContain('# Каталог response DTO')
            ->and($markdown)->toContain('Sync')
            ->and($markdown)->toContain('AsyncFinal')
            ->and($markdown)->toContain('Download')
            ->and($markdown)->toContain('CatalogUserDto');
    });

    it('определяет format по расширению пути', function () {
        $writer = new ResponseDtoCatalogWriter([new MarkdownResponseDtoCatalogExporter()]);

        $tmp = tempnam(sys_get_temp_dir(), 'apisutra-catalog-') . '.md';
        try {
            $writer->writeTo($tmp, apisutraBuildCatalogForWriterTest());

            expect(file_exists($tmp))->toBeTrue();
            $content = (string) file_get_contents($tmp);
            expect($content)->toContain('# Каталог response DTO');
        } finally {
            @unlink($tmp);
        }
    });

    it('бросает ConfigurationException когда format неизвестен', function () {
        $writer = new ResponseDtoCatalogWriter([new MarkdownResponseDtoCatalogExporter()]);

        $writer->render(apisutraBuildCatalogForWriterTest(), 'json');
    })->throws(ConfigurationException::class);

    it('бросает ConfigurationException когда нет ни format, ни extension', function () {
        $writer = new ResponseDtoCatalogWriter([new MarkdownResponseDtoCatalogExporter()]);

        $tmp = tempnam(sys_get_temp_dir(), 'apisutra-catalog-noext-');
        try {
            $writer->writeTo($tmp, apisutraBuildCatalogForWriterTest());
        } finally {
            @unlink($tmp);
        }
    })->throws(ConfigurationException::class);

    it('бросает ConfigurationException на дублирующиеся exporter-ы', function () {
        new ResponseDtoCatalogWriter([
            new MarkdownResponseDtoCatalogExporter(),
            new MarkdownResponseDtoCatalogExporter(),
        ]);
    })->throws(ConfigurationException::class);

    it('подбирает custom exporter, не перебивая built-in', function () {
        $jsonExporter = new class implements ResponseDtoCatalogExporterInterface {
            #[\Override]
            public function format(): string
            {
                return 'json';
            }

            #[\Override]
            public function export(ResponseDtoCatalog $catalog): string
            {
                return json_encode([
                    'all' => $catalog->listAllDtoClasses(),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }
        };

        $writer = new ResponseDtoCatalogWriter([
            new MarkdownResponseDtoCatalogExporter(),
            $jsonExporter,
        ]);

        $catalog = apisutraBuildCatalogForWriterTest();
        $json = $writer->render($catalog, 'json');
        $md = $writer->render($catalog, 'md');

        expect($json)->toContain('"all"')
            ->and($md)->toContain('# Каталог response DTO')
            ->and($writer->formats())->toContain('json')
            ->and($writer->formats())->toContain('md');
    });
});
