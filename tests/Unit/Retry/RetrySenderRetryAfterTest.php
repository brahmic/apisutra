<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\RetryConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Http\HttpMethod;
use Brahmic\ApiSutra\Enums\RateLimiting\BackoffStrategy;
use Brahmic\ApiSutra\Hooks\HookRegistry;
use Brahmic\ApiSutra\Pipeline\Auth\AuthHandler;
use Brahmic\ApiSutra\Pipeline\Diagnostics\AuditLogger;
use Brahmic\ApiSutra\Pipeline\Error\ErrorPolicy;
use Brahmic\ApiSutra\Pipeline\Hooks\HookRunner;
use Brahmic\ApiSutra\Pipeline\Transport\RetrySender;
use Brahmic\ApiSutra\RateLimiting\RateLimiter;
use Brahmic\ApiSutra\Retry\RetryHandler;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use Brahmic\ApiSutra\Tests\Support\RecordingPipelineExecutor;
use Brahmic\ApiSutra\Tests\Support\FakeSleeper;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

describe('RetrySender Retry-After', function () {
    it('повторяет запрос при 429 и заголовке Retry-After', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::sequence([
                MockResponse::rateLimited(2),
                MockResponse::success(['ok' => true]),
            ]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            retry: new RetryConfig(
                attempts: 2,
                baseDelay: 0,
                maxDelay: 0,
                backoff: BackoffStrategy::Constant,
                jitter: false,
                retryOn: [429],
            ),
            environment: Environment::Testing,
        );

        $request = new SimpleGetRequest('q');
        $prepared = new PreparedRequest(
            method: HttpMethod::GET,
            url: 'https://api.test/items',
            meta: [
                'requestClass' => SimpleGetRequest::class,
                'requestInstance' => $request,
            ],
        );

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            preparedRequest: $prepared,
        );

        $sleeper = new FakeSleeper();
        $retrySender = new RetrySender(
            config: $config,
            transport: $transport,
            retryHandler: new RetryHandler($transport),
            rateLimiter: new RateLimiter(),
            hookRunner: new HookRunner(new HookRegistry()),
            authHandler: new AuthHandler($config, new RecordingPipelineExecutor()),
            errorPolicy: new ErrorPolicy(),
            auditLogger: new AuditLogger($config),
            sleeper: $sleeper,
        );

        $response = $retrySender->sendWithRetry($request, $context);

        expect($response->status)->toBe(200);
        expect($transport->getRecorded())->toHaveCount(2);
        expect($sleeper->calls)->toBe(1);
        expect($sleeper->totalMs)->toBe(2000);
    });

    it('использует HTTP-date в Retry-After', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::sequence([
                MockResponse::make(
                    ['message' => 'Too Many'],
                    429,
                    ['Retry-After' => gmdate('D, d M Y H:i:s \G\M\T', time() + 2)],
                ),
                MockResponse::success(['ok' => true]),
            ]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            retry: new RetryConfig(
                attempts: 2,
                baseDelay: 0,
                maxDelay: 0,
                backoff: BackoffStrategy::Constant,
                jitter: false,
                retryOn: [429],
            ),
            environment: Environment::Testing,
        );

        $request = new SimpleGetRequest('q');
        $prepared = new PreparedRequest(
            method: HttpMethod::GET,
            url: 'https://api.test/items',
            meta: [
                'requestClass' => SimpleGetRequest::class,
                'requestInstance' => $request,
            ],
        );

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            preparedRequest: $prepared,
        );

        $sleeper = new FakeSleeper();
        $retrySender = new RetrySender(
            config: $config,
            transport: $transport,
            retryHandler: new RetryHandler($transport),
            rateLimiter: new RateLimiter(),
            hookRunner: new HookRunner(new HookRegistry()),
            authHandler: new AuthHandler($config, new RecordingPipelineExecutor()),
            errorPolicy: new ErrorPolicy(),
            auditLogger: new AuditLogger($config),
            sleeper: $sleeper,
        );

        $response = $retrySender->sendWithRetry($request, $context);

        expect($response->status)->toBe(200);
        expect($transport->getRecorded())->toHaveCount(2);
        expect($sleeper->totalMs)->toBeGreaterThanOrEqual(1000);
        expect($sleeper->totalMs)->toBeLessThanOrEqual(3000);
    });

    it('повторяет 429 без Retry-After через backoff', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::sequence([
                MockResponse::make(['message' => 'Too Many'], 429),
                MockResponse::success(['ok' => true]),
            ]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            retry: new RetryConfig(
                attempts: 2,
                baseDelay: 0,
                maxDelay: 0,
                backoff: BackoffStrategy::Constant,
                jitter: false,
                retryOn: [429],
            ),
            environment: Environment::Testing,
        );

        $request = new SimpleGetRequest('q');
        $prepared = new PreparedRequest(
            method: HttpMethod::GET,
            url: 'https://api.test/items',
            meta: [
                'requestClass' => SimpleGetRequest::class,
                'requestInstance' => $request,
            ],
        );

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            preparedRequest: $prepared,
        );

        $sleeper = new FakeSleeper();
        $retrySender = new RetrySender(
            config: $config,
            transport: $transport,
            retryHandler: new RetryHandler($transport),
            rateLimiter: new RateLimiter(),
            hookRunner: new HookRunner(new HookRegistry()),
            authHandler: new AuthHandler($config, new RecordingPipelineExecutor()),
            errorPolicy: new ErrorPolicy(),
            auditLogger: new AuditLogger($config),
            sleeper: $sleeper,
        );

        $response = $retrySender->sendWithRetry($request, $context);

        expect($response->status)->toBe(200);
        expect($transport->getRecorded())->toHaveCount(2);
        expect($sleeper->calls)->toBe(0);
    });
});
