<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Retry;

use Brahmic\ApiSutra\Config\RetryConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Concurrency\RetryHandlerInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use Brahmic\ApiSutra\Enums\RateLimiting\BackoffStrategy;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use Closure;

final class RetryHandler implements RetryHandlerInterface
{
    private readonly ?Closure $jitterResolver;

    public function __construct(
        private readonly TransportInterface $transport,
        ?callable $jitterResolver = null,
    ) {
        $this->jitterResolver = $jitterResolver === null
            ? null
            : Closure::fromCallable($jitterResolver);
    }

    #[\Override]
    public function handle(
        PreparedRequest $request,
        PipelineContext $context,
        RetryConfig $config,
        int $attempt,
    ): ProviderResponse {
        $delay = $this->calculateDelay($config, $attempt);
        if ($delay > 0) {
            usleep($delay * 1000);
        }

        return $this->transport->send($request);
    }

    private function calculateDelay(RetryConfig $config, int $attempt): int
    {
        $delay = match ($config->backoff) {
            BackoffStrategy::Constant => $config->baseDelay,
            BackoffStrategy::Linear => $config->baseDelay * $attempt,
            BackoffStrategy::Exponential => $config->baseDelay * (2 ** ($attempt - 1)),
        };

        $delay = min($delay, $config->maxDelay);

        if ($config->jitter) {
            $jitter = $this->jitterResolver !== null
                ? (int) ($this->jitterResolver)($config, $attempt)
                : random_int(0, (int) ($config->baseDelay * 0.5));
            $delay += max(0, $jitter);
        }

        return min($delay, $config->maxDelay);
    }
}
