<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Execution;

use Brahmic\ApiSutra\Collections\ErrorCollection;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Enums\Errors\ErrorCode;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use Brahmic\ApiSutra\Exceptions\Transport\TimeoutException;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\VO\Errors\RequestError;
use Brahmic\ApiSutra\VO\Errors\SystemErrorContextBuilder;
use Throwable;

final readonly class ExecutionErrorFactory
{
    public function buildExceptionResult(RequestInterface $request, Throwable $exception): ExecutionResult
    {
        $requestClass = $request instanceof RequestExecutionInterface
            ? $request->getRequest()::class
            : $request::class;
        $traceId = SystemErrorContextBuilder::resolveTraceId($request);
        $contextData = SystemErrorContextBuilder::build(
            traceId: $traceId,
            httpStatus: $exception instanceof ExecutionDeadlineException ? $exception->response?->status : null,
            requestClass: $requestClass,
        );

        if ($exception instanceof ExecutionDeadlineException) {
            $contextData['reason'] = 'execution_deadline_exceeded';
            $contextData['stage'] = $exception->stage;
        }

        return new ExecutionResult(
            data: null,
            status: ResultStatus::FAILED,
            errors: new ErrorCollection([
                new RequestError(
                    code: match (true) {
                        $exception instanceof TimeoutException => ErrorCode::Timeout,
                        $exception instanceof ConfigurationException => ErrorCode::ConfigurationError,
                        default => ErrorCode::ConnectionFailed,
                    },
                    message: $exception->getMessage(),
                    context: $contextData,
                    requestClass: $requestClass,
                    response: $exception instanceof ExecutionDeadlineException ? $exception->response : null,
                ),
            ]),
            requestClass: $requestClass,
            exception: $exception,
            response: $exception instanceof ExecutionDeadlineException ? $exception->response : null,
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
