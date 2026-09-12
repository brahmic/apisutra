<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pipeline\Flow;

use Brahmic\ApiSutra\Collections\ErrorCollection;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\ResultMeta;
use Brahmic\ApiSutra\Enums\Errors\ErrorCode;
use Brahmic\ApiSutra\Enums\Pipeline\PipelineStage;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Exceptions\Files\FileTransferException;
use Brahmic\ApiSutra\Exceptions\ControlFlow\EarlyReturnException;
use Brahmic\ApiSutra\Exceptions\Core\SdkException;
use Brahmic\ApiSutra\Exceptions\Extension\ExtensionException;
use Brahmic\ApiSutra\Exceptions\Request\RequestException;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\Exceptions\Serialization\ResponseDecodingException;
use Brahmic\ApiSutra\Exceptions\Serialization\SerializationException;
use Brahmic\ApiSutra\Exceptions\Transport\ConnectionException;
use Brahmic\ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use Brahmic\ApiSutra\Exceptions\Transport\InvalidRequestException;
use Brahmic\ApiSutra\Exceptions\Transport\TimeoutException;
use Brahmic\ApiSutra\Exceptions\Transport\TransportException;
use Brahmic\ApiSutra\Exceptions\Validation\ValidationException;
use Brahmic\ApiSutra\Pipeline\Diagnostics\AuditLogger;
use Brahmic\ApiSutra\Pipeline\Hydration\ResponseHydrator;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\VO\Audit\DebugInfo;
use Brahmic\ApiSutra\VO\Errors\RequestError;
use Brahmic\ApiSutra\VO\Errors\SystemErrorContextBuilder;
use Brahmic\ApiSutra\VO\Errors\ValidationError;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use Psr\Log\LogLevel;
use Throwable;

/**
 * Строитель ExecutionResult для всех веток пайплайна.
 *
 * Инварианты:
 * - SUCCESS/PARTIAL/FAILED формируются в одном месте по единым правилам;
 * - системный error context всегда содержит traceId/httpStatus/requestClass;
 * - при throwOnErrors исключения пробрасываются, иначе упаковываются в ExecutionResult.
 *
 * @see docs/guides/errors.md
 * @see docs/technical/error-handling.md
 * @see docs/technical/pipeline.md
 */
final readonly class ExecutionResultBuilder
{
    public function __construct(
        private ClientConfig $config,
        private AuditLogger $auditLogger,
        private ResponseHydrator $responseHydrator,
    ) {}

    /**
     * @param array<int, ValidationError> $validationErrors
     */
    public function buildValidationFailure(
        RequestInterface $request,
        PipelineContext $context,
        array &$audit,
        float $startTime,
        array $validationErrors,
    ): ExecutionResult {
        $error = $this->createRequestError(
            code: ErrorCode::ValidationFailed,
            message: 'Ошибка валидации запроса',
            request: $request,
            context: $context,
        );

        $result = $this->createFailedResult(
            request: $request,
            context: $context,
            audit: $audit,
            errors: new ErrorCollection([$error]),
            exception: new ValidationException($validationErrors),
            validationErrors: $validationErrors,
        );

        $this->auditLogger->addAudit($audit, PipelineStage::Failed, $context, $startTime, null);
        $this->auditLogger->log(LogLevel::ERROR, 'Ошибка валидации запроса', [
            'trace' => $context->traceId,
            'request' => $request::class,
            'errors' => array_map(
                static fn (ValidationError $error) => $error->field . ':' . $error->rule,
                $validationErrors,
            ),
        ]);

        if ($this->config->throwOnErrors) {
            throw $result->exception;
        }

        return $result;
    }

    public function buildSuccessResult(
        RequestInterface $request,
        PipelineContext $context,
        array &$audit,
        float $startTime,
        PreparedRequest $prepared,
        mixed $data,
        ?ResultMeta $meta = null,
    ): ExecutionResult {
        $duration = (microtime(true) - $startTime) * 1000;
        $debug = $this->config->debug
            ? new DebugInfo($context->response?->request ?? $context->preparedRequest ?? $prepared, $context->response, $duration)
            : null;

        $this->auditLogger->addAudit($audit, PipelineStage::Completed, $context, $startTime, $debug);
        try {
            $this->auditLogger->log(LogLevel::INFO, 'Запрос завершен', [
                'trace' => $context->traceId,
                'request' => $request::class,
                'status' => ResultStatus::SUCCESS->value,
                'duration_ms' => $duration,
            ]);
        } catch (Throwable $exception) {
            // Уже сохранённый файл нельзя объявить неуспешным из-за итогового logger.
            if ($context->fileTransfer?->target === null) {
                throw $exception;
            }
        }

        return $this->createSuccessResult(
            request: $request,
            context: $context,
            audit: $audit,
            data: $data,
            debug: $debug,
            meta: $meta,
            response: $context->response,
        );
    }

    public function buildRequestContractViolation(
        RequestInterface $request,
        PipelineContext $context,
        array &$audit,
        float $startTime,
        RequestContractViolation $violation,
    ): ExecutionResult {
        $message = $violation->message();
        $error = $this->createRequestError(
            code: ErrorCode::RequestContractViolation,
            message: $message,
            request: $request,
            context: $context,
            overrideContext: $violation->context(),
        );

        $result = $this->createFailedResult(
            request: $request,
            context: $context,
            audit: $audit,
            errors: new ErrorCollection([$error]),
            exception: new SdkException($message),
        );

        $this->auditLogger->addAudit($audit, PipelineStage::Failed, $context, $startTime, null);
        $this->auditLogger->log(LogLevel::ERROR, 'Ошибка контракта запроса', [
            'trace' => $context->traceId,
            'request' => $request::class,
            'contract' => $violation->contract,
            'violations' => $violation->violations,
        ]);

        if ($this->config->throwOnErrors) {
            throw $result->exception;
        }

        return $result;
    }

    public function buildEarlyReturnResult(
        RequestInterface $request,
        PipelineContext $context,
        array &$audit,
        float $startTime,
        PreparedRequest $prepared,
        EarlyReturnException $exception,
    ): ExecutionResult {
        $resultData = $this->responseHydrator->hydrateResponse($request, $context, $exception->data);
        $debug = $this->config->debug ? new DebugInfo($context->preparedRequest ?? $prepared, null, null) : null;

        $this->auditLogger->addAudit($audit, PipelineStage::Completed, $context, $startTime, $debug);
        $this->auditLogger->log(LogLevel::INFO, 'Запрос завершен', [
            'trace' => $context->traceId,
            'request' => $request::class,
            'status' => ResultStatus::SUCCESS->value,
        ]);

        return $this->createSuccessResult(
            request: $request,
            context: $context,
            audit: $audit,
            data: $resultData,
            debug: $debug,
            response: $context->response,
        );
    }

    public function buildExceptionResult(
        RequestInterface $request,
        PipelineContext $context,
        array &$audit,
        float $startTime,
        Throwable $exception,
    ): ExecutionResult {
        $code = $context->failureCode ?? match (true) {
            $exception instanceof FileTransferException => ErrorCode::FileTransferError,
            $exception instanceof SerializationException => ErrorCode::SerializationError,
            $exception instanceof ResponseDecodingException => ErrorCode::ResponseDecodingError,
            $exception instanceof HydrationException => ErrorCode::HydrationError,
            $exception instanceof TimeoutException => ErrorCode::Timeout,
            $exception instanceof ConnectionException => ErrorCode::ConnectionFailed,
            $exception instanceof InvalidRequestException => ErrorCode::InvalidRequest,
            $exception instanceof TransportException => ErrorCode::TransportError,
            $exception instanceof ExtensionException => ErrorCode::ExtensionError,
            default => ErrorCode::ExecutionError,
        };
        $response = $context->response;

        if ($exception instanceof ExecutionDeadlineException) {
            $code = ErrorCode::Timeout;
            $response = $context->response ?? $context->lastResponse ?? $exception->response;
        } elseif ($exception instanceof RequestException) {
            $response = $exception->response;
            $code = ErrorCode::fromHttpStatus($response->status);
        } elseif ($exception instanceof ConfigurationException) {
            $code = ErrorCode::ConfigurationError;
        }

        $error = $this->createRequestError(
            code: $code,
            message: $context->destination?->preserveUrl ? 'Ошибка выполнения запроса по готовому URL' : $exception->getMessage(),
            request: $request,
            context: $context,
            response: $response,
            overrideContext: $exception instanceof ExecutionDeadlineException
                ? array_filter(['reason' => 'execution_deadline_exceeded', 'stage' => $exception->stage,
                    'bytesWritten' => $exception->bytesWritten, 'partial' => $exception->partial], static fn (mixed $value): bool => $value !== null)
                : ($exception instanceof FileTransferException
                    ? ['stage' => $exception->stage, 'bytesWritten' => $exception->bytesWritten, 'partial' => $exception->partial]
                    : []),
        );

        $this->auditLogger->addAudit($audit, PipelineStage::Failed, $context, $startTime, null);
        $this->auditLogger->log(LogLevel::ERROR, 'Запрос завершился исключением', [
            'trace' => $context->traceId,
            'request' => $request::class,
            'exception' => $exception::class,
            'message' => $context->destination?->preserveUrl ? 'Ошибка выполнения запроса по готовому URL' : $exception->getMessage(),
        ]);

        $result = $this->createFailedResult(
            request: $request,
            context: $context,
            audit: $audit,
            errors: new ErrorCollection([$error]),
            exception: $exception,
            response: $response,
        );

        if ($this->config->throwOnErrors) {
            throw $exception;
        }

        return $result;
    }

    private function createRequestError(
        ErrorCode $code,
        string $message,
        RequestInterface $request,
        PipelineContext $context,
        ?ProviderResponse $response = null,
        array $overrideContext = [],
    ): RequestError {
        $systemContext = SystemErrorContextBuilder::build(
            traceId: $context->traceId,
            httpStatus: $response?->status,
            requestClass: $request::class,
        );

        $contextData = array_merge($systemContext, $overrideContext);
        if ($context->retryRefusalReason !== null) {
            $contextData['retryRefusalReason'] = $context->retryRefusalReason;
        }

        return new RequestError(
            code: $code,
            message: $message,
            response: $response,
            context: $contextData,
            requestClass: $request::class,
        );
    }

    private function createSuccessResult(
        RequestInterface $request,
        PipelineContext $context,
        array $audit,
        mixed $data,
        ?DebugInfo $debug = null,
        ?ResultMeta $meta = null,
        ?ProviderResponse $response = null,
    ): ExecutionResult {
        return new ExecutionResult(
            redaction: $this->config->redaction,
            data: $data,
            status: ResultStatus::SUCCESS,
            errors: new ErrorCollection([]),
            debug: $debug,
            traceId: $context->traceId,
            audit: $audit,
            meta: $meta,
            requestClass: $request::class,
            response: $response,
        );
    }

    /**
     * @param array<int, ValidationError> $validationErrors
     */
    private function createFailedResult(
        RequestInterface $request,
        PipelineContext $context,
        array $audit,
        ErrorCollection $errors,
        ?Throwable $exception = null,
        array $validationErrors = [],
        ?ProviderResponse $response = null,
    ): ExecutionResult {
        return new ExecutionResult(
            redaction: $this->config->redaction,
            data: null,
            status: ResultStatus::FAILED,
            errors: $errors,
            validationErrors: $validationErrors,
            debug: $this->config->debug
                ? new DebugInfo($response?->request ?? $context->preparedRequest, $response)
                : null,
            traceId: $context->traceId,
            audit: $audit,
            requestClass: $request::class,
            response: $response,
            exception: $exception,
        );
    }
}
