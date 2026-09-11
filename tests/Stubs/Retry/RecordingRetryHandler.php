<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Retry;

use Brahmic\ApiSutra\Config\RetryConfig;
use Brahmic\ApiSutra\Enums\RateLimiting\BackoffStrategy;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use Brahmic\ApiSutra\Contracts\Interfaces\Concurrency\RetryHandlerInterface;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;

final class RecordingRetryHandler implements RetryHandlerInterface
{
    /**
     * @var array<int>
     */
    public array $attempts = [];

    /**
     * @var array<int>
     */
    public array $delays = [];

    public function __construct(
        private MockTransport $transport,
    ) {}

    public function handle(
        PreparedRequest $request,
        PipelineContext $context,
        RetryConfig $config,
        int $attempt,
    ): ProviderResponse {
        $this->attempts[] = $attempt;
        $this->delays[] = $this->calculateDelay($config, $attempt);

        return $this->transport->send($request);
    }

    private function calculateDelay(RetryConfig $config, int $attempt): int
    {
        $delay = match ($config->backoff) {
            BackoffStrategy::Constant => $config->baseDelay,
            BackoffStrategy::Linear => $config->baseDelay * $attempt,
            BackoffStrategy::Exponential => $config->baseDelay * (2 ** ($attempt - 1)),
        };

        return min($delay, $config->maxDelay);
    }
}
