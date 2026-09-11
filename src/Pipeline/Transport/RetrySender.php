<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pipeline\Transport;

use Brahmic\ApiSutra\Config\ClientConfig;
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
                    $response = $retryConfig !== null
                        ? $this->retryHandler->handle($context->preparedRequest, $context, $retryConfig, $attempt)
                        : $this->transport->send($context->preparedRequest);
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
                    $this->auditLogger->log(LogLevel::WARNING, 'Повторная аутентификация при 401', [
                        'trace' => $context->traceId,
                        'request' => $request::class,
                        'attempt' => $attempt,
                        'auth_attempt' => $authRetryUsed + 1,
                        'auth_attempts' => $authRetryLimit,
                    ]);
                    $this->authHandler->handleAuthentication($request, $context, true);
                    $authRetryUsed++;
                    continue;
                }

                if ($attempt < $attempts && $this->retryDecisionMaker->shouldRetry($request, $response, $attempt, $retryConfig)) {
                    if ($response->status === 429) {
                        $retryAfter = $this->retryAfter($response);
                        if ($retryAfter !== null) {
                            $this->sleeper->sleepMs($retryAfter * 1000);
                        }
                    }
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
                if ($exception->maxAttempts !== null && $attempt >= $exception->maxAttempts) {
                    throw $exception;
                }
                $attempt++;
                continue;
            } catch (ControlFlowException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                $lastException = $exception;
                if ($context->failureCode !== ErrorCode::HookError && $retryConfig !== null && $this->retryDecisionMaker->isRetryException($exception, $retryConfig) && $attempt < $attempts) {
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

    private function retryAfter(ProviderResponse $response): ?int
    {
        $header = $response->header('Retry-After');
        if ($header === null || trim($header) === '') {
            return null;
        }

        if (is_numeric($header)) {
            return (int) $header;
        }

        $timestamp = strtotime($header);
        if ($timestamp === false) {
            return null;
        }

        $seconds = $timestamp - time();
        return $seconds > 0 ? $seconds : 0;
    }

}
