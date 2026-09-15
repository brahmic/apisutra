<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\CacheConfig;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Requests\CacheableRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Tests\Support\ArrayCache;
use Brahmic\ApiSutra\Transport\MockTransport;

describe('CacheManager', function () {
    it('возвращает ответ из кеша при повторном запросе', function () {
        $cacheStore = new ArrayCache();
        $transport = new MockTransport();
        $transport->fake([
            CacheableRequest::class => MockResponse::success(['value' => 1]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://provider.test',
            cacheConfig: new CacheConfig(store: $cacheStore, ttl: 60, prefix: 'tests'),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new CacheableRequest('payload');
        $request->setClient($client);

        $first = $request->send()->raw();
        $second = $request->send()->raw();

        expect($first->data)->toBe(['value' => 1])
            ->and($second->data)->toBe(['value' => 1])
            ->and($transport->getRecorded())->toHaveCount(1);
    });
});
