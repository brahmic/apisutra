<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\CacheConfig;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\ProviderB\Requests\ProviderBDownloadRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\DownloadCacheRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Tests\Support\SpyCache;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\VO\Files\FileResponse;

describe('CacheManager download', function () {
    it('не кеширует download по умолчанию', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            ProviderBDownloadRequest::class => MockResponse::sequence([
                MockResponse::make('file-1', 200, ['Content-Type' => 'application/octet-stream']),
                MockResponse::make('file-2', 200, ['Content-Type' => 'application/octet-stream']),
            ]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cacheStore: $cache,
            cacheConfig: new CacheConfig(ttl: 60, prefix: 'test-account'),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new ProviderBDownloadRequest('op-1');
        $request->setClient($client);

        $first = $request->send()->raw();
        $second = $request->send()->raw();

        expect($first->data)->toBeInstanceOf(FileResponse::class)
            ->and($second->data)->toBeInstanceOf(FileResponse::class)
            ->and($transport->getRecorded())->toHaveCount(2)
            ->and($cache->lastSetKey)->toBeNull();
    });

    it('отклоняет кеш download при withCache()', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            ProviderBDownloadRequest::class => MockResponse::sequence([
                MockResponse::make('file-1', 200, ['Content-Type' => 'application/octet-stream']),
                MockResponse::make('file-2', 200, ['Content-Type' => 'application/octet-stream']),
            ]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cacheStore: $cache,
            cacheConfig: new CacheConfig(ttl: 60, prefix: 'test-account'),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new ProviderBDownloadRequest('op-1');
        $request->setClient($client);

        $first = $request->withCache()->send()->raw();
        $second = $request->withCache()->send()->raw();

        expect($first->errors->first()?->code->value)->toBe('configuration_error')
            ->and($second->errors->first()?->code->value)->toBe('configuration_error')
            ->and($transport->getRecorded())->toHaveCount(0)
            ->and($cache->lastSetKey)->toBeNull();
    });

    it('отклоняет кеш download при #[Cache]', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            DownloadCacheRequest::class => MockResponse::sequence([
                MockResponse::make('file-1', 200, ['Content-Type' => 'application/octet-stream']),
                MockResponse::make('file-2', 200, ['Content-Type' => 'application/octet-stream']),
            ]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cacheStore: $cache,
            cacheConfig: new CacheConfig(ttl: 60, prefix: 'test-account'),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new DownloadCacheRequest();
        $request->setClient($client);

        $first = $request->send()->raw();
        $second = $request->send()->raw();

        expect($first->errors->first()?->code->value)->toBe('configuration_error')
            ->and($second->errors->first()?->code->value)->toBe('configuration_error')
            ->and($transport->getRecorded())->toHaveCount(0)
            ->and($cache->lastSetKey)->toBeNull();
    });
});
