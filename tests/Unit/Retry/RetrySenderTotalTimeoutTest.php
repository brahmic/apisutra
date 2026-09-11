<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Concurrency\RetryHandlerInterface;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Brahmic\ApiSutra\Config\RetryConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\RateLimiting\BackoffStrategy;
use Brahmic\ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use Brahmic\ApiSutra\Hooks\HookRegistry;
use Brahmic\ApiSutra\Pipeline\Auth\AuthHandler;
use Brahmic\ApiSutra\Pipeline\Diagnostics\AuditLogger;
use Brahmic\ApiSutra\Pipeline\Error\ErrorPolicy;
use Brahmic\ApiSutra\Pipeline\Hooks\HookRunner;
use Brahmic\ApiSutra\Pipeline\Transport\RetrySender;
use Brahmic\ApiSutra\RateLimiting\RateLimiter;
use Brahmic\ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use Brahmic\ApiSutra\Tests\Support\VirtualClock;
use Brahmic\ApiSutra\Timing\ExecutionBudget;
use Brahmic\ApiSutra\Tests\Support\RecordingPipelineExecutor;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

describe('RetrySender total timeout', function () {
    it('останавливает повторы при превышении общего таймаута', function () {
        $transport = new MockTransport();

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            retry: new RetryConfig(
                attempts: 2,
                baseDelay: 0,
                maxDelay: 0,
                backoff: BackoffStrategy::Constant,
                jitter: false,
                retryOn: [500],
                totalTimeoutMs: 1,
            ),
            environment: Environment::Testing,
        );

        $request = new SimpleGetRequest('q');
        $prepared = new PreparedRequest(
            method: $request->getMethod(),
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

        $clock = new VirtualClock();
        $context->budget = new ExecutionBudget($clock, 1);
        $retryHandler = new class($clock) implements RetryHandlerInterface {
            public int $calls = 0;
            public function __construct(private VirtualClock $clock) {}
            public function handle(PreparedRequest $request, PipelineContext $context, RetryConfig $config, int $attempt): ProviderResponse
            {
                $this->calls++;
                $this->clock->advance(2);
                return new ProviderResponse(500, [], '', $request);
            }
        };

        $retrySender = new RetrySender(
            config: $config,
            transport: $transport,
            retryHandler: $retryHandler,
            rateLimiter: new RateLimiter(),
            hookRunner: new HookRunner(new HookRegistry()),
            authHandler: new AuthHandler($config, new RecordingPipelineExecutor()),
            errorPolicy: new ErrorPolicy(),
            auditLogger: new AuditLogger($config),
        );

        expect(fn () => $retrySender->sendWithRetry($request, $context))
            ->toThrow(ExecutionDeadlineException::class, 'Исчерпан общий бюджет выполнения');

        expect($retryHandler->calls)->toBe(1);
    });
});

