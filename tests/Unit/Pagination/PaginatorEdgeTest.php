<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Execution\FailStrategy;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Pagination\Paginator;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Requests\CustomMetaPaginatedRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\MetaSequencePaginatedRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\PaginatedItemsRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\PaginatedOverrideRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\PaginatedRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\VO\Metadata\PaginationMeta;

describe('Paginator edge cases', function () {
    it('использует переопределённый withPage при options=null', function () {
        PaginatedOverrideRequest::reset();
        $transport = new MockTransport();
        $transport->fake([
            PaginatedOverrideRequest::class => MockResponse::success(['data' => []]),
        ]);
        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );

        $request = new PaginatedOverrideRequest();
        $request->setClient($client);

        $paginator = new Paginator($request, null);
        $paginator->pages(1);

        expect(PaginatedOverrideRequest::$withPageCalled)->toBeTrue();
    });

    it('использует переопределённый extractMeta', function () {
        CustomMetaPaginatedRequest::reset();
        $transport = new MockTransport();
        $transport->fake([
            CustomMetaPaginatedRequest::class => MockResponse::success(['data' => []]),
        ]);
        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );

        $request = new CustomMetaPaginatedRequest();
        $request->setClient($client);

        $request->paginate()->pages(1);

        expect(CustomMetaPaginatedRequest::$extractCalled)->toBeTrue();
    });

    it('использует next_cursor для следующего запроса', function () {
        $transport = new MockTransport();
        $transport->fake([
            PaginatedRequest::class => MockResponse::sequence([
                MockResponse::success([
                    'data' => [1],
                    'meta' => [
                        'has_more' => true,
                        'next_cursor' => 'c1',
                    ],
                ]),
                MockResponse::success([
                    'data' => [2],
                    'meta' => [
                        'has_more' => false,
                        'next_cursor' => null,
                    ],
                ]),
            ]),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );
        $request = new PaginatedRequest();
        $request->setClient($client);

        $request->paginate()->all();

        $recorded = $transport->getRecorded();
        expect($recorded)->toHaveCount(2);

        $query = $recorded[1]->meta['query'] ?? [];
        expect($query['cursor']['value'] ?? null)->toBe('c1');
    });

    it('корректно обрабатывает пустые результаты', function () {
        $transport = new MockTransport();
        $transport->fake([
            PaginatedRequest::class => MockResponse::make([
                'data' => [],
                'meta' => [
                    'page' => 1,
                    'per_page' => 10,
                    'total' => 0,
                    'has_more' => false,
                ],
            ]),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );
        $request = new PaginatedRequest();
        $request->setClient($client);

        $result = $request->paginate()->all();

        expect($result->items())->toBe([]);
        expect($transport->getRecorded())->toHaveCount(1);
    });

    it('корректно обрабатывает отсутствие meta', function () {
        $transport = new MockTransport();
        $transport->fake([
            PaginatedRequest::class => MockResponse::success([
                'data' => [1, 2],
            ]),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );
        $request = new PaginatedRequest();
        $request->setClient($client);

        $result = $request->paginate()->all();

        expect($result->items())->toBe([1, 2]);
        expect($result->meta()->hasMore)->toBeFalse();
        expect($transport->getRecorded())->toHaveCount(1);
    });

    it('не падает, если itemsPath не найден', function () {
        $transport = new MockTransport();
        $transport->fake([
            PaginatedItemsRequest::class => MockResponse::success([
                'data' => [1, 2],
                'meta' => [
                    'page' => 1,
                    'per_page' => 2,
                    'total' => 2,
                    'has_more' => false,
                ],
            ]),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );
        $request = new PaginatedItemsRequest();
        $request->setClient($client);

        $result = $request->paginate()->all();

        expect($result->items()['data'] ?? null)->toBe([1, 2]);
        expect($result->items()['meta']['has_more'] ?? null)->toBeFalse();
        expect($transport->getRecorded())->toHaveCount(1);
    });

    it('продолжает пагинацию при per_page=0', function () {
        MetaSequencePaginatedRequest::setMetaQueue([
            new PaginationMeta(total: null, currentPage: 1, perPage: 0, hasMore: true, nextCursor: null),
            new PaginationMeta(total: null, currentPage: 2, perPage: 0, hasMore: false, nextCursor: null),
        ]);
        $transport = new MockTransport();
        $transport->fake([
            MetaSequencePaginatedRequest::class => MockResponse::sequence([
                MockResponse::success([
                    'data' => [1],
                    'meta' => [
                        'page' => 1,
                        'per_page' => 0,
                        'has_more' => true,
                    ],
                ]),
                MockResponse::success([
                    'data' => [2],
                    'meta' => [
                        'page' => 2,
                        'per_page' => 0,
                        'has_more' => false,
                    ],
                ]),
            ]),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );
        $request = new MetaSequencePaginatedRequest();
        $request->setClient($client);

        $result = $request->paginate()->all();

        expect($result->items())->toBe([1, 2]);
        expect($transport->getRecorded())->toHaveCount(2);
    });

    it('возвращает пустой результат при pages(0)', function () {
        $transport = new MockTransport();
        $transport->fake([
            PaginatedRequest::class => MockResponse::success(['data' => [1]]),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );
        $request = new PaginatedRequest();
        $request->setClient($client);

        $result = $request->paginate()->pages(0);

        expect($result->items())->toBe([]);
        expect($transport->getRecorded())->toHaveCount(0);
    });

    it('возвращает пустой результат при некорректном range', function () {
        $transport = new MockTransport();
        $transport->fake([
            PaginatedRequest::class => MockResponse::success(['data' => [1]]),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );
        $request = new PaginatedRequest();
        $request->setClient($client);

        $result = $request->paginate()->range(5, 2);

        expect($result->items())->toBe([]);
        expect($transport->getRecorded())->toHaveCount(0);
    });

    it('останавливается при противоречивой meta', function () {
        $transport = new MockTransport();
        $transport->fake([
            PaginatedRequest::class => MockResponse::success([
                'data' => [1],
                'meta' => [
                    'page' => 1,
                    'per_page' => 10,
                    'total' => 0,
                    'has_more' => true,
                ],
            ]),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );
        $request = new PaginatedRequest();
        $request->setClient($client);

        $result = $request->paginate()->all();

        expect($result->items())->toBe([1]);
        expect($result->status)->toBe(ResultStatus::SUCCESS);
        expect($transport->getRecorded())->toHaveCount(1);
    });

    it('FailAll останавливается на первой ошибке', function () {
        MetaSequencePaginatedRequest::setMetaQueue([
            new PaginationMeta(total: null, currentPage: 1, perPage: 10, hasMore: true, nextCursor: null),
            new PaginationMeta(total: null, currentPage: 2, perPage: 10, hasMore: false, nextCursor: null),
        ]);
        $transport = new MockTransport();
        $transport->fake([
            MetaSequencePaginatedRequest::class => MockResponse::sequence([
                MockResponse::success([
                    'data' => [1],
                    'meta' => [
                        'page' => 1,
                        'per_page' => 1,
                        'has_more' => true,
                    ],
                ]),
                MockResponse::make([
                    'message' => 'fail',
                    'meta' => [
                        'page' => 2,
                        'per_page' => 1,
                        'has_more' => true,
                    ],
                ], 500),
                MockResponse::success([
                    'data' => [2],
                    'meta' => [
                        'page' => 3,
                        'per_page' => 1,
                        'has_more' => false,
                    ],
                ]),
            ]),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );
        $request = new MetaSequencePaginatedRequest();
        $request->setClient($client);

        $result = $request->paginate()->failStrategy(FailStrategy::FailAll)->all();

        expect($result->items())->toBe([1]);
        expect($transport->getRecorded())->toHaveCount(2);
    });

    it('Partial продолжает после ошибки', function () {
        MetaSequencePaginatedRequest::setMetaQueue([
            new PaginationMeta(total: null, currentPage: 1, perPage: 10, hasMore: true, nextCursor: null),
            new PaginationMeta(total: null, currentPage: 2, perPage: 10, hasMore: false, nextCursor: null),
        ]);
        $transport = new MockTransport();
        $transport->fake([
            MetaSequencePaginatedRequest::class => MockResponse::sequence([
                MockResponse::success([
                    'data' => [1],
                    'meta' => [
                        'page' => 1,
                        'per_page' => 1,
                        'has_more' => true,
                    ],
                ]),
                MockResponse::make([
                    'message' => 'fail',
                    'meta' => [
                        'page' => 2,
                        'per_page' => 1,
                        'has_more' => true,
                    ],
                ], 500),
                MockResponse::success([
                    'data' => [2],
                    'meta' => [
                        'page' => 3,
                        'per_page' => 1,
                        'has_more' => false,
                    ],
                ]),
            ]),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );
        $request = new MetaSequencePaginatedRequest();
        $request->setClient($client);

        $result = $request->paginate()->failStrategy(FailStrategy::Partial)->all();

        expect($transport->getRecorded())->toHaveCount(3);
        expect($result->items())->toBe([1, 2]);
    });
});
