<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Requests\RetryableRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;

describe('RetrySender', function () {
    it('повторяет запрос при ошибке и возвращает успешный ответ', function () {
        $transport = new MockTransport();
        $transport->fake([
            RetryableRequest::class => MockResponse::sequence([
                MockResponse::serverError(),
                MockResponse::success(['value' => 1]),
            ]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://provider.test',
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new RetryableRequest('payload');
        $request->setClient($client);

        $result = $request->send()->raw();

        expect($result->data)->toBe(['value' => 1]);
        expect($transport->getRecorded())->toHaveCount(2);
    });
});
