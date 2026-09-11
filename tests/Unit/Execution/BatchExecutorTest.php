<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Execution\FailStrategy;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Execution\BatchExecutor;
use Brahmic\ApiSutra\Result\BatchResult;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;

describe('BatchExecutor', function () {
    it('останавливается на ошибке при FailAll', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::sequence([
                MockResponse::success(['id' => 1, 'name' => 'A']),
                MockResponse::serverError(),
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

        $executor = new BatchExecutor(
            client: $client,
            requests: [
                new SimpleGetRequest('one'),
                new SimpleGetRequest('two'),
                new SimpleGetRequest('three'),
            ],
        );

        $batch = $executor->send();

        expect($batch->results()->countTotal())->toBe(2)
            ->and($transport->getRecorded())->toHaveCount(2);
    });

    it('продолжает выполнение при Partial', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::sequence([
                MockResponse::success(['id' => 1, 'name' => 'A']),
                MockResponse::serverError(),
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

        $executor = (new BatchExecutor(
            client: $client,
            requests: [
                new SimpleGetRequest('one'),
                new SimpleGetRequest('two'),
                new SimpleGetRequest('three'),
            ],
        ))->failStrategy(FailStrategy::Partial);

        $batch = $executor->send();

        expect($batch->results()->countTotal())->toBe(3)
            ->and($batch->status)->toBe(ResultStatus::PARTIAL)
            ->and($transport->getRecorded())->toHaveCount(3);
    });

    it('sendAsync возвращает BatchResult в parallel режиме', function () {
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

        $executor = (new BatchExecutor(
            client: $client,
            requests: [
                new SimpleGetRequest('one'),
                new SimpleGetRequest('two'),
            ],
        ))->parallel()->failStrategy(FailStrategy::Partial)->concurrency(1);

        $result = $executor->sendAsync()->wait();

        expect($result)->toBeInstanceOf(BatchResult::class)
            ->and($result->results()->countTotal())->toBe(2)
            ->and($transport->getRecorded())->toHaveCount(2);
    });
});
