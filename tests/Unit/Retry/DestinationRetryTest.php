<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\RetryConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Concurrency\RetryHandlerInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\DestinationAwareInterface;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Hooks\HookRegistry;
use Brahmic\ApiSutra\Http\RequestDestination;
use Brahmic\ApiSutra\Pipeline\Auth\AuthHandler;
use Brahmic\ApiSutra\Pipeline\Diagnostics\AuditLogger;
use Brahmic\ApiSutra\Pipeline\Error\ErrorPolicy;
use Brahmic\ApiSutra\Pipeline\Hooks\HookRunner;
use Brahmic\ApiSutra\Pipeline\Transport\RetrySender;
use Brahmic\ApiSutra\RateLimiting\RateLimiter;
use Brahmic\ApiSutra\Tests\Stubs\Requests\CacheProbeRequest;
use Brahmic\ApiSutra\Tests\Support\RecordingPipelineExecutor;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

it('проверяет фактический адрес после собственного retry handler', function (): void {
    $transport = new MockTransport();
    $handler = new class($transport) implements RetryHandlerInterface, DestinationAwareInterface {
        public function __construct(private MockTransport $transport) {}
        public function assertSupportsDestination(RequestDestination $destination): void {}
        public function handle(PreparedRequest $request, PipelineContext $context, RetryConfig $config, int $attempt): ProviderResponse
        {
            return $this->transport->send($request->with(url: 'https://other.test/changed'));
        }
    };
    $config = new ClientConfig(baseUrl: 'https://api.test', retry: new RetryConfig());
    $request = new CacheProbeRequest();
    $destination = new RequestDestination('https://storage.test/file?sig=fixture', $config->baseUrl, true);
    $context = new PipelineContext($request, $config, 'fixture-target', preparedRequest: new PreparedRequest($request->getMethod(), $destination->url, destination: $destination));
    $context->destination = $destination;
    $sender = new RetrySender($config, $transport, $handler, new RateLimiter(), new HookRunner(new HookRegistry()),
        new AuthHandler($config, new RecordingPipelineExecutor()), new ErrorPolicy(), new AuditLogger($config));
    expect(fn () => $sender->sendWithRetry($request, $context))->toThrow(ConfigurationException::class);
    expect($transport->getRecorded())->toBe([]);
});
