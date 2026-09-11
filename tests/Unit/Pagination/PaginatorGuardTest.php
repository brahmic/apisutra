<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\PaginationConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Requests\PaginatedOverrideRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\PaginatedRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;

describe('Paginator guard', function () {
    it('останавливается по totalPages даже при hasMore=true', function () {
        $calls = 0;
        $transport = new MockTransport();
        $transport->fake([
            PaginatedOverrideRequest::class => function () use (&$calls) {
                $calls++;
                return MockResponse::success([
                    'data' => [$calls],
                    'meta' => [
                        'page' => $calls,
                        'per_page' => 1,
                        'total' => 2,
                        'has_more' => true,
                    ],
                ]);
            },
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );
        $request = new PaginatedOverrideRequest();
        $request->setClient($client);

        $result = $request->paginate()->all();

        expect($result->status)->toBe(ResultStatus::SUCCESS);
        expect($transport->getRecorded())->toHaveCount(2);
    });

    it('останавливается по maxPages из конфигурации', function () {
        $calls = 0;
        $transport = new MockTransport();
        $transport->fake([
            PaginatedOverrideRequest::class => function () use (&$calls) {
                $calls++;
                return MockResponse::success([
                    'data' => [$calls],
                    'meta' => [
                        'page' => $calls,
                        'per_page' => 1,
                        'has_more' => true,
                    ],
                ]);
            },
        ]);

        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                paginationConfig: new PaginationConfig(maxPages: 2),
                environment: Environment::Testing,
            ),
            $transport,
        );
        $request = new PaginatedOverrideRequest();
        $request->setClient($client);

        $result = $request->paginate()->all();

        expect($result->status)->toBe(ResultStatus::PARTIAL);
        expect($result->errors->first()?->message)->toBe('Пагинация остановлена: достигнут лимит страниц');
        expect($transport->getRecorded())->toHaveCount(2);
    });

    it('останавливается при отсутствии cursor прогресса', function () {
        $transport = new MockTransport();
        $transport->fake([
            PaginatedRequest::class => MockResponse::success([
                'data' => [1],
                'meta' => [
                    'has_more' => true,
                    'next_cursor' => null,
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

        expect($result->status)->toBe(ResultStatus::PARTIAL);
        expect($transport->getRecorded())->toHaveCount(1);
    });

    it('останавливается при одинаковом next_cursor', function () {
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
                        'has_more' => true,
                        'next_cursor' => 'c1',
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

        $result = $request->paginate()->all();

        expect($result->status)->toBe(ResultStatus::PARTIAL);
        expect($result->errors->first()?->message)->toBe('Пагинация остановлена: cursor не изменился');
        expect($transport->getRecorded())->toHaveCount(2);
    });

    it('останавливается при отсутствии page прогресса', function () {
        $transport = new MockTransport();
        $transport->fake([
            PaginatedOverrideRequest::class => MockResponse::success([
                'data' => [1],
                'meta' => [
                    'page' => 1,
                    'per_page' => 10,
                    'has_more' => true,
                ],
            ]),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );
        $request = new PaginatedOverrideRequest();
        $request->setClient($client);

        $result = $request->paginate()->all();

        expect($result->status)->toBe(ResultStatus::PARTIAL);
        expect($transport->getRecorded())->toHaveCount(2);
    });
});
