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
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use Brahmic\ApiSutra\Tests\Support\RecordingPipelineExecutor;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use Brahmic\ApiSutra\Contracts\Interfaces\Concurrency\RetryHandlerInterface;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Brahmic\ApiSutra\Tests\Stubs\Retry\RecordingRetryHandler;

describe('RetrySender backoff strategies', function () {
    it('использует стратегию backoff при повторных попытках', function (
        BackoffStrategy $strategy,
        array $expectedDelays,
    ) {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::sequence([
                MockResponse::serverError(),
                MockResponse::serverError(),
                MockResponse::success(['ok' => true]),
            ]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            retry: new RetryConfig(
                attempts: 3,
                baseDelay: 100,
                maxDelay: 1000,
                backoff: $strategy,
                jitter: false,
                retryOn: [500],
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

        $recordingHandler = new RecordingRetryHandler($transport);
        $retrySender = new RetrySender(
            config: $config,
            transport: $transport,
            retryHandler: $recordingHandler,
            rateLimiter: new RateLimiter(),
            hookRunner: new HookRunner(new HookRegistry()),
            authHandler: new AuthHandler($config, new RecordingPipelineExecutor()),
            errorPolicy: new ErrorPolicy(),
            auditLogger: new AuditLogger($config),
        );

        $response = $retrySender->sendWithRetry($request, $context);

        expect($response->status)->toBe(200);
        expect($recordingHandler->delays)->toBe($expectedDelays);
        expect($recordingHandler->attempts)->toBe([1, 2, 3]);
    })->with([
        'constant' => [BackoffStrategy::Constant, [100, 100, 100]],
        'linear' => [BackoffStrategy::Linear, [100, 200, 300]],
        'exponential' => [BackoffStrategy::Exponential, [100, 200, 400]],
    ]);
});

