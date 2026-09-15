<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\CacheConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Hooks\HookRecorder;
use Brahmic\ApiSutra\Tests\Stubs\Requests\PipelineHookedRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Tests\Support\SpyCache;
use Brahmic\ApiSutra\Transport\MockTransport;

describe('Pipeline integration', function () {
    it('сохраняет порядок стадий и хуков', function () {
        HookRecorder::reset();
        $transport = new MockTransport();
        $transport->fake([
            PipelineHookedRequest::class => MockResponse::success(['id' => 1, 'name' => 'User']),
        ]);

        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $client = new TestClient($config, $transport);

        $request = new PipelineHookedRequest('q');
        $request->setClient($client);
        $request->send();

        expect(HookRecorder::all())->toBe([
            'beforeSend:attr',
            'beforeSend:req',
            'afterResponse:attr',
            'afterResponse:req',
            'beforeHydrate:attr',
            'beforeHydrate:req',
            'afterHydrate:attr',
            'afterHydrate:req',
        ]);
    });

    it('вызывает AfterResponse при cache hit', function () {
        HookRecorder::reset();
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            PipelineHookedRequest::class => MockResponse::sequence([
                MockResponse::success(['id' => 1, 'name' => 'User']),
                MockResponse::success(['id' => 2, 'name' => 'User']),
            ]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cacheStore: $cache,
            cacheConfig: new CacheConfig(ttl: 60, prefix: 'test-account'),
            environment: Environment::Testing,
        );
        $client = new TestClient($config, $transport);

        $request = new PipelineHookedRequest('q');
        $request->setClient($client);

        $request->withCache()->send();
        HookRecorder::reset();
        $request->withCache()->send();

        expect($transport->getRecorded())->toHaveCount(1);
        expect(HookRecorder::all())->toContain('afterResponse:attr', 'afterResponse:req');
    });
});
