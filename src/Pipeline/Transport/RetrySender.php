<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pipeline\Transport;

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\RetryConfig;
use Brahmic\ApiSutra\Retry\RetryHandler;
use Brahmic\ApiSutra\Retry\RequestBodyReplay;
use Brahmic\ApiSutra\Retry\RetryAfterDelay;
use Brahmic\ApiSutra\Contracts\Interfaces\Concurrency\RetryHandlerInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;
use Brahmic\ApiSutra\Core\AbstractClient;
use Brahmic\ApiSutra\Enums\Hooks\Hook;
use Brahmic\ApiSutra\Enums\Errors\ErrorCode;
use Brahmic\ApiSutra\Exceptions\ControlFlow\RetryableException;
use Brahmic\ApiSutra\Exceptions\ControlFlow\ControlFlowException;
use Brahmic\ApiSutra\Exceptions\Transport\ConnectionException;
use Brahmic\ApiSutra\Exceptions\Transport\TimeoutException;
use Brahmic\ApiSutra\Pipeline\Auth\AuthHandler;
use Brahmic\ApiSutra\Pipeline\Diagnostics\AuditLogger;
use Brahmic\ApiSutra\Pipeline\Error\ErrorPolicy;
use Brahmic\ApiSutra\Pipeline\Hooks\HookRunner;
use Brahmic\ApiSutra\RateLimiting\RateLimiter;
use Brahmic\ApiSutra\Transport\TransportExceptionNormalizer;
use Brahmic\ApiSutra\Timing\SystemSleeper;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use Psr\Log\LogLevel;
use Throwable;

final readonly class RetrySender
{
    private RetryConfigResolver $retryConfigResolver;
    private RetryDecisionMaker $retryDecisionMaker;
    private RateLimitApplier $rateLimitApplier;
    private DelayApplier $delayApplier;
    private SleeperInterface $sleeper;
    private RetryAfterDelay $retryAfterDelay;

    public function __construct(
        private ClientConfig $config,
        private TransportInterface $transport,
        private RetryHandlerInterface $retryHandler,
        private RateLimiter $rateLimiter,
        private HookRunner $hookRunner,
        private AuthHandler $authHandler,
        private ErrorPolicy $errorPolicy,
        private AuditLogger $auditLogger,
        private ?AbstractClient $client = null,
        ?SleeperInterface $sleeper = null,
        ?RetryAfterDelay $retryAfterDelay = null,
    ) {
        $this->retryConfigResolver = new RetryConfigResolver($this->config);
        $this->retryDecisionMaker = new RetryDecisionMaker(
            config: $this->config,
            errorPolicy: $this->errorPolicy,
            client: $this->client,
        );
        $this->rateLimitApplier = new RateLimitApplier($this->config, $this->rateLimiter);
        $this->delayApplier = new DelayApplier($this->config);
        $this->sleeper = $sleeper ?? new SystemSleeper();
        $this->retryAfterDelay = $retryAfterDelay ?? new RetryAfterDelay();
    }

    public function sendWithRetry(RequestInterface $request, PipelineContext $context): ProviderResponse
    {
        $retryConfig = $this->retryConfigResolver->resolve($request, $context->options);
        $attempts = $retryConfig?->attempts ?? 1;

        $attempt = 1;
        $lastException = null;
        $authRetryUsed = 0;
        $authRetryLimit = max(0, $this->config->authRetryAttempts);
        $startedAt = microtime(true);
        $totalTimeoutMs = $retryConfig?->totalTimeoutMs;
        $bodyReplay = new RequestBodyReplay($context->preparedRequest);
        $minimumDelayMs = 0;
        $applyBackoff = false;

        while ($attempt <= $attempts) {
            if ($this->isTotalTimeoutExceeded($startedAt, $totalTimeoutMs)) {
                $lastException ??= new TimeoutException('Превышен общий таймаут повторов');
                break;
            }

            try {
                $this->delayApplier->apply($request, $context->options);
                $this->rateLimitApplier->apply($request, $context);

                // Ответ относится к текущей HTTP-попытке; предыдущий не подставляется при сетевом сбое.
                $context->response = null;
                try {
                    $response = $this->sendAttempt($context, $retryConfig, $attempt, $minimumDelayMs, $applyBackoff);
                } catch (Throwable $exception) {
                    throw TransportExceptionNormalizer::normalize($exception);
                }
                $lastException = null;

                $context->response = $response;
                $this->hookRunner->runHookStage(Hook::AfterResponse, $request, $context);

                if ($response->status === 401 && $this->config->authRetryOn401) {
                    if ($authRetryLimit <= 0 || $authRetryUsed >= $authRetryLimit) {
                        return $response;
                    }
                    if (!$this->prepareRepeat($request, $context, $bodyReplay)) {
                        return $response;
                    }
                    $this->auditLogger->log(LogLevel::WARNING, 'Повторная аутентификация при 401', [
                        'trace' => $context->traceId,
                        'request' => $request::class,
                        'attempt' => $attempt,
                        'auth_attempt' => $authRetryUsed + 1,
                        'auth_attempts' => $authRetryLimit,
                    ]);
                    $this->authHandler->handleAuthentication($request, $context, true);
                    if (!$this->prepareRepeat($request, $context, $bodyReplay)) {
                        return $response;
                    }
                    $authRetryUsed++;
                    $minimumDelayMs = 0;
                    $applyBackoff = false;
                    continue;
                }

                if ($attempt < $attempts && $this->retryDecisionMaker->shouldRetry($request, $response, $attempt, $retryConfig)) {
                    if (!$this->prepareRepeat($request, $context, $bodyReplay)) {
                        return $response;
                    }
                    $minimumDelayMs = $this->retryAfterDelay->forResponse($response);
                    $applyBackoff = true;
                    $this->auditLogger->log(LogLevel::WARNING, 'Повтор запроса после ответа', [
                        'trace' => $context->traceId,
                        'request' => $request::class,
                        'attempt' => $attempt,
                        'status' => $response->status,
                    ]);
                    $attempt++;
                    continue;
                }

                return $response;
            } catch (RetryableException $exception) {
                $lastException = $exception;
                $this->auditLogger->log(LogLevel::WARNING, 'Исключение с повтором', [
                    'trace' => $context->traceId,
                    'request' => $request::class,
                    'attempt' => $attempt,
                    'exception' => $exception::class,
                ]);
                if ($retryConfig === null || $attempt >= $attempts
                    || ($exception->maxAttempts !== null && $attempt >= $exception->maxAttempts)
                    || !$this->prepareRepeat($request, $context, $bodyReplay)) {
                    throw $exception;
                }
                $minimumDelayMs = $this->retryAfterDelay->fromSeconds($exception->retryAfter);
                $applyBackoff = true;
                $attempt++;
                continue;
            } catch (ControlFlowException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                $lastException = $exception;
                if ($context->failureCode !== ErrorCode::HookError && $retryConfig !== null && $this->retryDecisionMaker->isRetryException($exception, $retryConfig) && $attempt < $attempts) {
                    if (!$this->prepareRepeat($request, $context, $bodyReplay)) {
                        throw $exception;
                    }
                    $minimumDelayMs = 0;
                    $applyBackoff = true;
                    $this->auditLogger->log(LogLevel::WARNING, 'Повтор запроса после исключения', [
                        'trace' => $context->traceId,
                        'request' => $request::class,
                        'attempt' => $attempt,
                        'exception' => $exception::class,
                    ]);
                    $attempt++;
                    continue;
                }

                throw $exception;
            }
        }

        if ($lastException instanceof Throwable) {
            throw $lastException;
        }

        throw new ConnectionException('Не удалось выполнить запрос');
    }

    private function isTotalTimeoutExceeded(float $startedAt, ?int $totalTimeoutMs): bool
    {
        if ($totalTimeoutMs === null || $totalTimeoutMs <= 0) {
            return false;
        }

        $elapsedMs = (microtime(true) - $startedAt) * 1000;
        return $elapsedMs >= $totalTimeoutMs;
    }

    private function prepareRepeat(RequestInterface $request, PipelineContext $context, RequestBodyReplay $body): bool
    {
        $reason = $this->retryDecisionMaker->isSafe($request, $context->preparedRequest->method)
            ? $body->restore($context->preparedRequest)
            : 'operation_not_safe';
        if ($reason === null) {
            return true;
        }
        $context->retryRefusalReason = $reason;
        $this->auditLogger->log(LogLevel::WARNING, 'Повтор запроса запрещён', [
            'trace' => $context->traceId,
            'request' => $request::class,
            'retryRefusalReason' => $reason,
        ]);
        return false;
    }

    private function sendAttempt(
        PipelineContext $context,
        ?RetryConfig $config,
        int $attempt,
        int $minimumDelayMs,
        bool $applyBackoff,
    ): ProviderResponse {
        if ($config === null) {
            return $this->transport->send($context->preparedRequest);
        }
        if ($this->retryHandler instanceof RetryHandler) {
            return $this->retryHandler->sendWithDelay(
                $context->preparedRequest, $context, $config, $attempt,
                $minimumDelayMs, $applyBackoff, $this->sleeper,
            );
        }
        // Собственный handler сохраняет ответственность за свой backoff.
        if ($minimumDelayMs > 0) {
            $this->sleeper->sleepMs($minimumDelayMs);
        }
        return $this->retryHandler->handle($context->preparedRequest, $context, $config, $attempt);
    }

}
