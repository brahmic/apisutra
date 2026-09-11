<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\PaginationConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Requests\ConfigPaginatedRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;

describe('Pagination config', function () {
    it('использует параметры из ClientConfig при отсутствии атрибута', function () {
        $transport = new MockTransport();
        $transport->fake([
            ConfigPaginatedRequest::class => MockResponse::success([
                'data' => [],
                'meta' => [
                    'page' => 1,
                    'limit' => 10,
                    'total' => 0,
                    'has_more' => false,
                ],
            ]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://provider.test',
            paginationConfig: new PaginationConfig(pageParam: 'offset', limitParam: 'size'),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new ConfigPaginatedRequest();
        $request->setClient($client);

        $request->withPage(3)->withLimit(25)->send()->raw();

        $recorded = $transport->getRecorded();
        $query = $recorded[0]->meta['query'] ?? [];

        expect($query['offset']['value'] ?? null)->toBe(3);
        expect($query['size']['value'] ?? null)->toBe(25);
    });

    it('использует paginationConfig для параметров page/limit', function () {
        $transport = new MockTransport();
        $transport->fake([
            ConfigPaginatedRequest::class => MockResponse::success([
                'data' => [],
                'meta' => [
                    'page' => 1,
                    'limit' => 10,
                    'total' => 0,
                    'has_more' => false,
                ],
            ]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://provider.test',
            paginationConfig: new PaginationConfig(pageParam: 'offset', limitParam: 'size'),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new ConfigPaginatedRequest();
        $request->setClient($client);

        $request->withPage(2)->withLimit(15)->send()->raw();

        $recorded = $transport->getRecorded();
        $query = $recorded[0]->meta['query'] ?? [];

        expect($query['offset']['value'] ?? null)->toBe(2);
        expect($query['size']['value'] ?? null)->toBe(15);
    });
});
