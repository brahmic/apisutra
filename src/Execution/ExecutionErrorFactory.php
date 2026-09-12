<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Execution;

use Brahmic\ApiSutra\Collections\ErrorCollection;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Enums\Errors\ErrorCode;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Exceptions\RateLimiting\RateLimitBackendException;
use Brahmic\ApiSutra\Exceptions\Request\RateLimitException;
use Brahmic\ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use Brahmic\ApiSutra\Exceptions\Transport\TimeoutException;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\VO\Errors\RequestError;
use Brahmic\ApiSutra\VO\Errors\SystemErrorContextBuilder;
use Brahmic\ApiSutra\Exceptions\Auth\AuthDependencyException;
use Brahmic\ApiSutra\Exceptions\Auth\AuthRefreshFailedException;
use Brahmic\ApiSutra\Exceptions\Request\RequestException;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\Exceptions\Serialization\ResponseDecodingException;
use Brahmic\ApiSutra\Exceptions\Transport\ConnectionException;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Throwable;

final readonly class ExecutionErrorFactory
{
    public function buildExceptionResult(RequestInterface $request, Throwable $exception, ?ProviderResponse $response = null): ExecutionResult
    {
        if ($exception instanceof AuthDependencyException) {
            return $exception->dependencyResult;
        }
        $requestClass = $request instanceof RequestExecutionInterface
            ? $request->getRequest()::class
            : $request::class;
        $traceId = SystemErrorContextBuilder::resolveTraceId($request);
        $localRateLimit = $exception instanceof RateLimitException && $exception->response === null;
        $response = match (true) {
            $exception instanceof ExecutionDeadlineException => $exception->response,
            $localRateLimit, $exception instanceof RateLimitBackendException => $exception->lastResponse,
            $exception instanceof RequestException => $exception->response,
            default => $response,
        };
        $contextData = SystemErrorContextBuilder::build(
            traceId: $traceId,
            httpStatus: $response?->status,
            requestClass: $requestClass,
        );

        if ($exception instanceof AuthRefreshFailedException) {
            $contextData['reason'] = 'auth_refresh_failed';
        } elseif ($exception instanceof ExecutionDeadlineException) {
            $contextData['reason'] = 'execution_deadline_exceeded';
            $contextData['stage'] = $exception->stage;
        } elseif ($localRateLimit) {
            $contextData += ['reason' => 'local_rate_limit_exceeded', 'stage' => 'rate_limit', 'retryAfter' => $exception->retryAfter];
        } elseif ($exception instanceof RateLimitBackendException) {
            $contextData += ['reason' => 'rate_limit_backend_error', 'stage' => 'rate_limit_store'];
        }

        return new ExecutionResult(
            data: null,
            status: ResultStatus::FAILED,
            errors: new ErrorCollection([
                new RequestError(
                    code: match (true) {
                        $localRateLimit => ErrorCode::RateLimited,
                        $exception instanceof RateLimitBackendException => ErrorCode::ExecutionError,
                        $exception instanceof TimeoutException => ErrorCode::Timeout,
                        $exception instanceof ConfigurationException => ErrorCode::ConfigurationError,
                        $exception instanceof RequestException && $response !== null => ErrorCode::fromHttpStatus($response->status),
                        $exception instanceof HydrationException => ErrorCode::HydrationError,
                        $exception instanceof ResponseDecodingException => ErrorCode::ResponseDecodingError,
                        $exception instanceof ConnectionException => ErrorCode::ConnectionFailed,
                        default => ErrorCode::ExecutionError,
                    },
                    message: $exception->getMessage(),
                    context: $contextData,
                    requestClass: $requestClass,
                    response: $response,
                ),
            ]),
            requestClass: $requestClass,
            exception: $exception,
            response: $response,
        );
    }

    public function unsupportedItemMessage(string $scope, int $index, mixed $item): string
    {
        return sprintf(
            'Неподдерживаемый элемент %s[%d]: %s',
            $scope,
            $index,
            $this->describeItem($item),
        );
    }

    public function invalidItemMessage(string $scope, int $index, mixed $item): string
    {
        return sprintf(
            'Некорректный элемент %s[%d]: %s не резолвится в RequestInterface',
            $scope,
            $index,
            $this->describeItem($item),
        );
    }

    private function describeItem(mixed $item): string
    {
        if (is_string($item)) {
            return 'string(' . $item . ')';
        }

        if (is_object($item)) {
            return $item::class;
        }

        return get_debug_type($item);
    }
}
