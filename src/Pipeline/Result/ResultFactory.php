<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pipeline\Result;

use Brahmic\ApiSutra\Collections\ErrorCollection;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Enums\Errors\ErrorCode;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Pipeline\Error\ErrorPolicy;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\VO\Errors\RequestError;
use Brahmic\ApiSutra\VO\Errors\SystemErrorContextBuilder;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class ResultFactory
{
    public function __construct(
        private ErrorPolicy $errorPolicy,
    ) {}

    public function buildFailedResult(RequestInterface $request, PipelineContext $context, array $audit): ExecutionResult
    {
        $response = $context->response;
        $code = ErrorCode::ServerError;
        if ($response !== null) {
            $code = match ($response->status) {
                401 => ErrorCode::Unauthorized,
                403 => ErrorCode::Forbidden,
                404 => ErrorCode::NotFound,
                422 => ErrorCode::ValidationFailed,
                429 => ErrorCode::RateLimited,
                500 => ErrorCode::ServerError,
                502 => ErrorCode::BadGateway,
                503 => ErrorCode::ServiceUnavailable,
                504 => ErrorCode::GatewayTimeout,
                default => ErrorCode::ServerError,
            };
        }

        $contextData = SystemErrorContextBuilder::build(
            traceId: $context->traceId,
            httpStatus: $response?->status,
            requestClass: $request::class,
        );

        $error = new RequestError(
            code: $code,
            message: $response?->json('message') ?? 'Ошибка запроса',
            response: $response,
            context: $contextData,
            requestClass: $request::class,
        );

        $exception = $response ? $this->errorPolicy->getRequestExceptionInternal($request, $response) : null;

        return new ExecutionResult(
            data: null,
            status: ResultStatus::FAILED,
            errors: new ErrorCollection([$error]),
            traceId: $context->traceId,
            audit: $audit,
            requestClass: $request::class,
            response: $response,
            exception: $exception,
        );
    }
}
