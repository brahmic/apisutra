<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Execution;

use Brahmic\ApiSutra\Collections\ErrorCollection;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Enums\Errors\ErrorCode;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
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
            httpStatus: null,
            requestClass: $requestClass,
        );

        return new ExecutionResult(
            data: null,
            status: ResultStatus::FAILED,
            errors: new ErrorCollection([
                new RequestError(
                    code: ErrorCode::ConnectionFailed,
                    message: $exception->getMessage(),
                    context: $contextData,
                    requestClass: $requestClass,
                ),
            ]),
            requestClass: $requestClass,
            exception: $exception,
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
