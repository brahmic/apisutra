<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\RetryConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Concurrency\RetryHandlerInterface;
use Brahmic\ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use Brahmic\ApiSutra\Hooks\HookRegistry;
use Brahmic\ApiSutra\Pipeline\Auth\AuthHandler;
use Brahmic\ApiSutra\Pipeline\Diagnostics\AuditLogger;
use Brahmic\ApiSutra\Pipeline\Error\ErrorPolicy;
use Brahmic\ApiSutra\Pipeline\Hooks\HookRunner;
use Brahmic\ApiSutra\Pipeline\Transport\RetrySender;
use Brahmic\ApiSutra\RateLimiting\RateLimiter;
use Brahmic\ApiSutra\Tests\Stubs\Core\SequenceHttpClient;
use Brahmic\ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use Brahmic\ApiSutra\Tests\Support\RecordingPipelineExecutor;
use Brahmic\ApiSutra\Tests\Support\VirtualClock;
use Brahmic\ApiSutra\Timing\ExecutionBudget;
use Brahmic\ApiSutra\Transport\HttpTransport;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;

it('HTTP адаптер пересчитывает бюджет после ожиданий собственного retry handler', function (int $wait): void {
    $clock = new VirtualClock();
    $http = new SequenceHttpClient([new Response(204)]);
    $factory = new HttpFactory();
    $transport = new HttpTransport($http, $factory, $factory);
    $handler = new class($clock, $transport, $wait) implements RetryHandlerInterface {
        public function __construct(private VirtualClock $clock, private HttpTransport $transport, private int $wait) {}
        public function handle(PreparedRequest $request, PipelineContext $context, RetryConfig $config, int $attempt): ProviderResponse
        {
            $this->clock->advance($this->wait);
            return $this->transport->send($request);
        }
    };
    $config = new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig(totalTimeoutMs: 1000));
    $request = new RetryPolicyRequest();
    $context = new PipelineContext($request, $config, 'fixture-handler', preparedRequest: new PreparedRequest($request->getMethod(), 'https://fixture.test'));
    $context->budget = new ExecutionBudget($clock, 1000);
    $sender = new RetrySender($config, $transport, $handler, new RateLimiter(), new HookRunner(new HookRegistry()),
        new AuthHandler($config, new RecordingPipelineExecutor()), new ErrorPolicy(), new AuditLogger($config), sleeper: $clock);
    if ($wait < 1000) {
        expect($sender->sendWithRetry($request, $context)->status)->toBe(204)->and($http->options[0]->timeoutMs)->toBe(250);
    } else {
        expect(fn () => $sender->sendWithRetry($request, $context))->toThrow(ExecutionDeadlineException::class);
        expect($http->calls)->toBe(0);
    }
})->with([750, 1001]);
