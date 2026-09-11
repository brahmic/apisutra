<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Requests\OffsetPaginatedRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;

describe('Paginator offset-based', function () {
    it('требует limit для offset-based пагинации', function () {
        $request = new OffsetPaginatedRequest();

        expect(fn () => $request->paginate()->pages(1))
            ->toThrow(ConfigurationException::class, 'Для offset-based пагинации требуется limit');
    });

    it('преобразует page в offset', function () {
        $transport = new MockTransport();
        $transport->fake([
            OffsetPaginatedRequest::class => MockResponse::success([
                'data' => [],
                'meta' => [
                    'page' => 1,
                    'limit' => 10,
                    'total' => 0,
                    'has_more' => false,
                ],
            ]),
        ]);

        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://provider.test',
                environment: Environment::Testing,
            ),
            $transport,
        );

        $request = new OffsetPaginatedRequest();
        $request->setClient($client);

        $request->paginate()->perPage(10)->pages(1);

        $recorded = $transport->getRecorded();
        expect($recorded)->toHaveCount(1);

        $query = $recorded[0]->meta['query'] ?? [];
        expect($query['offset']['value'] ?? null)->toBe(0);
        expect($query['limit']['value'] ?? null)->toBe(10);
    });
});
