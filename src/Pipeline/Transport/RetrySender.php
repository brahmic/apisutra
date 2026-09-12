<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pipeline\Transport;

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\RetryConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Concurrency\RetryHandlerInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;
use Brahmic\ApiSutra\Core\AbstractClient;
use Brahmic\ApiSutra\Enums\Errors\ErrorCode;
use Brahmic\ApiSutra\Enums\Hooks\Hook;
use Brahmic\ApiSutra\Http\DestinationGuard;
use Brahmic\ApiSutra\Http\RequestBodyGuard;
use Brahmic\ApiSutra\Files\FileTransferGuard;
use Brahmic\ApiSutra\Exceptions\ControlFlow\ControlFlowException;
use Brahmic\ApiSutra\Exceptions\Auth\AuthRefreshFailedException;
use Brahmic\ApiSutra\Exceptions\ControlFlow\RetryableException;
use Brahmic\ApiSutra\Exceptions\RateLimiting\RateLimitBackendException;
use Brahmic\ApiSutra\Exceptions\Request\RateLimitException;
use Brahmic\ApiSutra\Exceptions\Transport\ConnectionException;
use Brahmic\ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use Brahmic\ApiSutra\Pipeline\Auth\AuthHandler;
use Brahmic\ApiSutra\Pipeline\Diagnostics\AuditLogger;
use Brahmic\ApiSutra\Pipeline\Error\ErrorPolicy;
use Brahmic\ApiSutra\Pipeline\Hooks\HookRunner;
use Brahmic\ApiSutra\Pipeline\Preparation\TimeoutResolver;
use Brahmic\ApiSutra\RateLimiting\RateLimiter;
use Brahmic\ApiSutra\Retry\RequestBodyReplay;
use Brahmic\ApiSutra\Retry\RetryAfterDelay;
use Brahmic\ApiSutra\Retry\RetryHandler;
use Brahmic\ApiSutra\Timing\ExecutionBudget;
use Brahmic\ApiSutra\Timing\SystemClock;
use Brahmic\ApiSutra\Timing\SystemSleeper;
use Brahmic\ApiSutra\Transport\TransportCapabilities;
use Brahmic\ApiSutra\Transport\TransportExceptionNormalizer;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use Psr\Log\LogLevel;
use Brahmic\ApiSutra\Exceptions\Testing\RecordingException;
use Throwable;

final readonly class RetrySender
{
    private RetryConfigResolver $retryConfigResolver;
    private RetryDecisionMaker $retryDecisionMaker;
    private RateLimitApplier $rateLimitApplier;
    private DelayApplier $delayApplier;
    private SleeperInterface $sleeper;
    private ?RetryAfterDelay $retryAfterDelay;

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
        $this->sleeper = $sleeper ?? new SystemSleeper();
        $this->delayApplier = new DelayApplier($this->config, $this->sleeper);
        $this->retryAfterDelay = $retryAfterDelay;
    }

    /** Проверка поддержки до auth/refresh и любых HTTP-попыток этого исполнения. */
    public function assertDestinationSupported(PipelineContext $context): void
    {
        DestinationGuard::checkContext($context);
        FileTransferGuard::checkContext($context);
        DestinationGuard::checkCapability($this->transport, $context->destination);
        $transfer = $context->preparedRequest !== null
            ? FileTransferGuard::options($context->preparedRequest) : $context->fileTransfer;
        FileTransferGuard::checkCapability($this->transport, $transfer);
        if ($this->retryConfigResolver->resolve($context->request, $context->options) !== null) {
            DestinationGuard::checkCapability($this->retryHandler, $context->destination);
            FileTransferGuard::checkCapability($this->retryHandler, $transfer);
        }
    }

    public function sendWithRetry(RequestInterface $request, PipelineContext $context): ProviderResponse
    {
        $this->assertDestinationSupported($context);
        $retryConfig = $this->retryConfigResolver->resolve($request, $context->options);
        $attempts = $retryConfig?->attempts ?? 1;

        $attempt = 1;
        $lastException = null;
        $authRetryUsed = 0;
        $authRetryLimit = max(0, $this->config->authRetryAttempts);
        $context->budget ??= new ExecutionBudget(new SystemClock(), $this->config->retry?->totalTimeoutMs, $context->parent?->budget);
        $retryAfterDelay = $this->retryAfterDelay ?? new RetryAfterDelay(static fn (): int => $context->budget->clock->unixTime());
        $bodyReplay = new RequestBodyReplay($context->preparedRequest);
        $minimumDelayMs = 0;
        $applyBackoff = false;

        while ($attempt <= $attempts) {
            DestinationGuard::checkContext($context);
            FileTransferGuard::checkContext($context);
            RequestBodyGuard::check($context->preparedRequest);
            $context->budget->check('before_attempt', $lastException);

            try {
                $this->delayApplier->apply($request, $context->options, $context->budget);
                $this->rateLimitApplier->apply($request, $context);
                $context->budget->check('before_http');
                $context->preparedRequest = $context->preparedRequest->with(transportOptions: TimeoutResolver::resolve($context));
                TransportCapabilities::check($this->transport, $context->preparedRequest->transportOptions->effective());

                // Ответ относится к текущей HTTP-попытке; предыдущий не подставляется при сетевом сбое.
                $context->response = null;
                try {
                    $response = $this->sendAttempt($context, $retryConfig, $attempt, $minimumDelayMs, $applyBackoff);
                } catch (Throwable $exception) {
                    if ($exception instanceof RecordingException) {
                        $context->response = $exception->response;
                        $context->lastResponse = $exception->response;
                    }
                    if (!$exception instanceof ExecutionDeadlineException) {
                        $context->budget->check('http', $exception);
                    }
                    throw TransportExceptionNormalizer::normalize($exception);
                }
                $lastException = null;

                $context->response = $response;
                $context->lastResponse = $response;
                $context->budget->check('http_response');
                $this->hookRunner->runHookStage(Hook::AfterResponse, $request, $context);
                $context->budget->check('after_response');

                if (
                    $response->status === 401 && $this->config->authRetryOn401
                    && $authRetryUsed < $authRetryLimit
                    && (!$context->destination?->requiresIsolation() || $this->authHandler->resolveForCache($request, $context) !== null)
                ) {
                    if (!$this->prepareRepeat($request, $context, $bodyReplay)) {
                        return $response;
                    }
                    if ($this->authHandler->recoverAuthentication($request, $context)) {
                        if (!$this->prepareRepeat($request, $context, $bodyReplay)) {
                            return $response;
                        }
                        $authRetryUsed++;
                        $this->auditLogger->log(LogLevel::WARNING, 'Повтор после восстановления авторизации', [
                            'trace' => $context->traceId,
                            'request' => $request::class,
                            'auth_attempt' => $authRetryUsed,
                        ]);
                        $minimumDelayMs = 0;
                        $applyBackoff = false;
                        continue;
                    }
                }

                if ($attempt < $attempts && $this->retryDecisionMaker->shouldRetry($request, $response, $attempt, $retryConfig)) {
                    if (!$this->prepareRepeat($request, $context, $bodyReplay)) {
                        return $response;
                    }
                    $minimumDelayMs = $retryAfterDelay->forResponse($response);
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
                $minimumDelayMs = $retryAfterDelay->fromSeconds($exception->retryAfter);
                $applyBackoff = true;
                $attempt++;
                continue;
            } catch (ExecutionDeadlineException $exception) {
                if ($exception->getPrevious() === null && $lastException !== null) {
                    throw new ExecutionDeadlineException($exception->stage, $lastException);
                }
                throw $exception;
            } catch (ControlFlowException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                if ($exception instanceof AuthRefreshFailedException || $exception instanceof RecordingException) {
                    throw $exception;
                }
                // Локальный отказ не является HTTP-попыткой и не допускает слепого повтора записи.
                if ($exception instanceof RateLimitBackendException) {
                    throw new RateLimitBackendException($exception->getPrevious(), $context->response ?? $context->lastResponse);
                }
                if ($exception instanceof RateLimitException && $exception->response === null) {
                    throw new RateLimitException(
                        $exception->getMessage(), null, $exception->retryAfter, $exception->getCode(),
                        $exception, $context->response ?? $context->lastResponse,
                    );
                }
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
        RequestBodyGuard::check($context->preparedRequest);
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
            $context->budget->wait($minimumDelayMs, $this->sleeper, 'retry_wait');
        }
        return $this->retryHandler->handle($context->preparedRequest, $context, $config, $attempt);
    }

}
