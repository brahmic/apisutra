<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Requests\CompositeAggregateRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;

describe('Composite flow', function () {
    it('агрегирует результаты дочерних запросов', function () {
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

        $request = new CompositeAggregateRequest();
        $request->setClient($client);

        $result = $request->send()->raw();

        expect($result->status)->toBe(ResultStatus::SUCCESS);
        expect($result->data)->toBe(['A', 'B']);
        expect($result->nested)->toHaveCount(2);
        expect($transport->getRecorded())->toHaveCount(2);
    });
});
