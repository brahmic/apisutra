<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\PoolConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Execution\PoolExecutor;
use Brahmic\ApiSutra\Result\PoolResult;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;

describe('PoolExecutor', function () {
    it('останавливается при stopOnFailure', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::sequence([
                MockResponse::serverError(),
                MockResponse::success(['id' => 1, 'name' => 'A']),
                MockResponse::success(['id' => 2, 'name' => 'B']),
            ]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://provider.test',
            pool: new PoolConfig(stopOnFailure: true),
            environment: Environment::Testing,
        );
        $client = new TestClient($config, $transport);

        $pool = new PoolExecutor(
            client: $client,
            requests: [
                new SimpleGetRequest('one'),
                new SimpleGetRequest('two'),
                new SimpleGetRequest('three'),
            ],
            concurrency: 1,
            config: $config->pool,
        );

        $result = $pool->send();

        expect($result->results()->countTotal())->toBe(1);
        expect($transport->getRecorded())->toHaveCount(1);
    });

    it('останавливается при stopOnFailure с concurrency > 1', function () {
        $transport = new MockTransport();
        $calls = 0;
        $transport->fake([
            SimpleGetRequest::class => function () use (&$calls) {
                $calls++;
                return $calls === 1
                    ? MockResponse::serverError()
                    : MockResponse::success(['id' => $calls, 'name' => 'ok']);
            },
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://provider.test',
            pool: new PoolConfig(stopOnFailure: true),
            environment: Environment::Testing,
        );
        $client = new TestClient($config, $transport);

        $pool = new PoolExecutor(
            client: $client,
            requests: [
                new SimpleGetRequest('one'),
                new SimpleGetRequest('two'),
                new SimpleGetRequest('three'),
                new SimpleGetRequest('four'),
                new SimpleGetRequest('five'),
            ],
            concurrency: 3,
            config: $config->pool,
        );

        $result = $pool->send();

        expect($result->results()->countTotal())->toBe(3);
        expect($transport->getRecorded())->toHaveCount(3);
    });

    it('возвращает partial для пустой коллекции', function () {
        $transport = new MockTransport();
        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://provider.test', environment: Environment::Testing),
            $transport,
        );

        $pool = new PoolExecutor(
            client: $client,
            requests: [],
            concurrency: 2,
        );

        $result = $pool->send();

        expect($result->results()->countTotal())->toBe(0);
        expect($result->status)->toBe(ResultStatus::PARTIAL);
        expect($transport->getRecorded())->toHaveCount(0);
    });

    it('возвращает partial для смешанных результатов', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::sequence([
                MockResponse::success(['id' => 1, 'name' => 'A']),
                MockResponse::serverError(),
            ]),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://provider.test', environment: Environment::Testing),
            $transport,
        );

        $pool = new PoolExecutor(
            client: $client,
            requests: [
                new SimpleGetRequest('one'),
                new SimpleGetRequest('two'),
            ],
            concurrency: 1,
        );

        $result = $pool->send();

        expect($result->status)->toBe(ResultStatus::PARTIAL);
        expect($result->results()->countTotal())->toBe(2);
        expect($transport->getRecorded())->toHaveCount(2);
    });

    it('вызывает response handler для каждого результата', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::sequence([
                MockResponse::success(['id' => 1, 'name' => 'A']),
                MockResponse::success(['id' => 2, 'name' => 'B']),
            ]),
        ]);

        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://provider.test',
                environment: Environment::Testing,
            ),
            $transport,
        );

        $calls = 0;
        $pool = (new PoolExecutor(
            client: $client,
            requests: [
                new SimpleGetRequest('one'),
                new SimpleGetRequest('two'),
            ],
            concurrency: 1,
        ))->withResponseHandler(function () use (&$calls): void {
            $calls++;
        });

        $pool->send();

        expect($calls)->toBe(2);
    });

    it('sendAsync возвращает PoolResult', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::sequence([
                MockResponse::success(['id' => 1, 'name' => 'A']),
                MockResponse::success(['id' => 2, 'name' => 'B']),
            ]),
        ]);

        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://provider.test',
                environment: Environment::Testing,
            ),
            $transport,
        );

        $pool = new PoolExecutor(
            client: $client,
            requests: [
                new SimpleGetRequest('one'),
                new SimpleGetRequest('two'),
            ],
            concurrency: 1,
        );

        $result = $pool->sendAsync()->wait();

        expect($result)->toBeInstanceOf(PoolResult::class);
        expect($result->results()->countTotal())->toBe(2);
        expect($transport->getRecorded())->toHaveCount(2);
    });
});
