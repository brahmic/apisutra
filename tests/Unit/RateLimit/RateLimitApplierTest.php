<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\RateLimitConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Execution\RequestRole;
use Brahmic\ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use Brahmic\ApiSutra\Exceptions\Request\RateLimitException;
use Brahmic\ApiSutra\Pipeline\Transport\RateLimitApplier;
use Brahmic\ApiSutra\RateLimiting\RateLimiter;
use Brahmic\ApiSutra\Request\RequestOptions;
use Brahmic\ApiSutra\Tests\Stubs\Requests\RateLimitRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use Brahmic\ApiSutra\Tests\Support\SpyCache;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

describe('RateLimitApplier', function () {
    it('пропускает rate limit при отключении в options', function () {
        $cache = new SpyCache();
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            rateLimit: new RateLimitConfig(limit: 1, period: 60, behavior: RateLimitBehavior::Throw, store: $cache),
            environment: Environment::Testing,
        );

        $context = new PipelineContext(
            request: new SimpleGetRequest('q'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
            options: RequestOptions::empty()->withoutRateLimit(),
        );

        $applier = new RateLimitApplier($config, new RateLimiter());
        $applier->apply($context->request, $context);

        expect($cache->lastGetKey)->toBeNull();
        expect($cache->lastSetKey)->toBeNull();
    });

    it('использует атрибут и ограничивает запросы', function () {
        $cache = new SpyCache();
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            rateLimit: new RateLimitConfig(limit: 100, period: 60, behavior: RateLimitBehavior::Throw, store: $cache),
            environment: Environment::Testing,
        );

        $request = new RateLimitRequest();
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $applier = new RateLimitApplier($config, new RateLimiter());
        $applier->apply($request, $context);

        expect(fn () => $applier->apply($request, $context))
            ->toThrow(RateLimitException::class);
    });
});
