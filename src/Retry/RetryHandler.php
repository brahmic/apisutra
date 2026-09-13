<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Retry;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\DestinationAwareInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\FileStreamingInterface;
use Brahmic\ApiSutra\VO\Files\FileTransferOptions;
use Brahmic\ApiSutra\Http\RequestDestination;
use Brahmic\ApiSutra\Http\DestinationGuard;
use Brahmic\ApiSutra\Http\RequestBodyGuard;
use Brahmic\ApiSutra\Files\FileTransferGuard;
use Brahmic\ApiSutra\Config\RetryConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Concurrency\RetryHandlerInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;
use Brahmic\ApiSutra\Enums\RateLimiting\BackoffStrategy;
use Brahmic\ApiSutra\Timing\ExecutionBudget;
use Brahmic\ApiSutra\Timing\SystemClock;
use Brahmic\ApiSutra\Timing\SystemSleeper;
use Brahmic\ApiSutra\Transport\TransportCapabilities;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use Closure;
use Brahmic\ApiSutra\Enums\Http\TransmissionState;
use Override;

final class RetryHandler implements RetryHandlerInterface, DestinationAwareInterface, FileStreamingInterface
{
    public function assertSupportsFileTransfer(FileTransferOptions $options): void
    {
        FileTransferGuard::checkCapability($this->transport, $options);
    }

    public function assertSupportsDestination(RequestDestination $destination): void
    {
        DestinationGuard::checkCapability($this->transport, $destination);
    }

    private readonly ?Closure $jitterResolver;

    public function __construct(
        private readonly TransportInterface $transport,
        ?callable $jitterResolver = null,
        private readonly SleeperInterface $sleeper = new SystemSleeper(),
    ) {
        $this->jitterResolver = $jitterResolver === null
            ? null
            : Closure::fromCallable($jitterResolver);
    }

    #[Override]
    public function handle(
        PreparedRequest $request,
        PipelineContext $context,
        RetryConfig $config,
        int $attempt,
    ): ProviderResponse {
        return $this->sendWithDelay($request, $context, $config, $attempt);
    }

    /** Встроенная отправка объединяет серверное ожидание с backoff; auth retry не добавляет backoff. */
    public function sendWithDelay(
        PreparedRequest $request,
        PipelineContext $context,
        RetryConfig $config,
        int $attempt,
        int $minimumDelayMs = 0,
        bool $applyBackoff = true,
        ?SleeperInterface $sleeper = null,
    ): ProviderResponse {
        $backoff = $applyBackoff && $attempt > 1 ? $this->calculateDelay($config, $attempt - 1) : 0;
        $delay = max($minimumDelayMs, $backoff);
        if ($delay > 0) {
            ($context->budget ?? new ExecutionBudget(new SystemClock()))->wait($delay, $sleeper ?? $this->sleeper, 'retry_wait');
        }

        DestinationGuard::checkContext($context);
        FileTransferGuard::checkContext($context);
        RequestBodyGuard::check($request);
        DestinationGuard::checkRequest($request);
        DestinationGuard::checkCapability($this, $request->destination);
        FileTransferGuard::checkCapability($this, FileTransferGuard::options($request));
        $context->budget?->check('http');
        if ($request->transportOptions !== null) {
            $request = $request->with(transportOptions: $request->transportOptions->effective());
            $context->preparedRequest = $request;
            TransportCapabilities::check($this->transport, $request->transportOptions);
        }
        $context->transmissionState = TransmissionState::Unknown;
        return $this->transport->send($request);
    }

    private function calculateDelay(RetryConfig $config, int $attempt): int
    {
        $delay = match ($config->backoff) {
            BackoffStrategy::Constant => $config->baseDelay,
            BackoffStrategy::Linear => $config->baseDelay * $attempt,
            BackoffStrategy::Exponential => $config->baseDelay * (2 ** ($attempt - 1)),
        };

        $delay = (int) min($delay, $config->maxDelay);

        if ($config->jitter) {
            $jitter = $this->jitterResolver !== null
                ? (int) ($this->jitterResolver)($config, $attempt)
                : random_int(0, (int) ($config->baseDelay * 0.5));
            $delay += min(max(0, $jitter), $config->maxDelay - $delay);
        }

        return min($delay, $config->maxDelay);
    }
}
