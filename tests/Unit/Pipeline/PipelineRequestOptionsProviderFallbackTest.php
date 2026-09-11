<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\CacheConfig;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Hooks\Hook;
use Brahmic\ApiSutra\Hooks\HookRegistry;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\HookedClient;
use Brahmic\ApiSutra\Tests\Stubs\Hooks\CaptureContextHook;
use Brahmic\ApiSutra\Tests\Stubs\Requests\RequestOptionsProviderFallbackRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Tests\Support\SpyCache;
use Brahmic\ApiSutra\Transport\MockTransport;

describe('Pipeline options fallback for RequestOptionsProviderInterface', function () {
    it('подхватывает options из RequestOptionsProviderInterface в execute', function () {
        CaptureContextHook::reset();
        $hooks = new HookRegistry();
        $hooks->on(Hook::BeforeSend, new CaptureContextHook());

        $transport = new MockTransport();
        $transport->fake([
            RequestOptionsProviderFallbackRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            environment: Environment::Testing,
            debug: true,
        );
        $client = new HookedClient($config, $transport, $hooks);

        $request = new RequestOptionsProviderFallbackRequest('q');
        $request->setClient($client);

        $result = $request->send();

        expect(CaptureContextHook::$context?->options)->not->toBeNull();
        expect(CaptureContextHook::$context?->traceId)->toBe('trace-from-provider-options');
        expect($result->raw()->requestDebug()['url'] ?? null)->toStartWith('https://override.test');
    });

    it('подхватывает options из RequestOptionsProviderInterface в clearCache', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            RequestOptionsProviderFallbackRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            environment: Environment::Testing,
            cache: new CacheConfig(store: $cache, ttl: 60, prefix: 'test-account'),
        );
        $client = new TestClient($config, $transport);

        $request = new RequestOptionsProviderFallbackRequest('q');
        $request->setClient($client);

        $request->withBaseUrl('https://override.test')->withCache()->send();
        $setKey = $cache->lastSetKey;

        expect($setKey)->not->toBeNull();

        $client->clearCacheForRequest($request);

        $request->withBaseUrl('https://override.test')->withCache()->send();
        expect($transport->getRecorded())->toHaveCount(2);
    });
});
