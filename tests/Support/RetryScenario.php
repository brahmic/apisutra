<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Support;

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Hooks\HookRegistry;
use Brahmic\ApiSutra\Pipeline\Auth\AuthHandler;
use Brahmic\ApiSutra\Pipeline\Diagnostics\AuditLogger;
use Brahmic\ApiSutra\Pipeline\Error\ErrorPolicy;
use Brahmic\ApiSutra\Pipeline\Hooks\HookRunner;
use Brahmic\ApiSutra\Pipeline\Transport\RetrySender;
use Brahmic\ApiSutra\RateLimiting\RateLimiter;
use Brahmic\ApiSutra\Retry\RetryAfterDelay;
use Brahmic\ApiSutra\Retry\RetryHandler;
use Brahmic\ApiSutra\Tests\Stubs\Retry\ConsumingHttpClient;
use Brahmic\ApiSutra\Transport\HttpTransport;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Message\ResponseInterface;
use Brahmic\ApiSutra\Tests\Stubs\Dto\TokenResponseDto;
use Throwable;

final readonly class RetryScenario
{
    public ConsumingHttpClient $http;
    public FakeSleeper $sleeper;
    public PipelineContext $context;
    public HookRegistry $hooks;
    private RetrySender $sender;

    /** @param list<ResponseInterface|Throwable> $results */
    public function __construct(ClientConfig $config, RequestInterface $request, PreparedRequest $prepared, array $results)
    {
        $this->http = new ConsumingHttpClient($results);
        $factory = new HttpFactory();
        $transport = new HttpTransport($this->http, $factory, $factory);
        $this->sleeper = new FakeSleeper();
        $this->context = new PipelineContext($request, $config, 'fixture-trace', preparedRequest: $prepared);
        $this->hooks = new HookRegistry();
        $this->sender = new RetrySender(
            $config, $transport, new RetryHandler($transport), new RateLimiter(),
            new HookRunner($this->hooks), new AuthHandler($config, new RecordingPipelineExecutor(new TokenResponseDto('fixture'))),
            new ErrorPolicy(), new AuditLogger($config), sleeper: $this->sleeper,
            retryAfterDelay: new RetryAfterDelay(static fn (): int => 1_800_000_000),
        );
    }

    public function run(): ProviderResponse
    {
        return $this->sender->sendWithRetry($this->context->request, $this->context);
    }
}
