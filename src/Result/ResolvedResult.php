<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Result;

use Brahmic\ApiSutra\Collections\ErrorCollection;
use Brahmic\ApiSutra\Exceptions\Core\SdkException;
use Brahmic\ApiSutra\VO\Errors\ClientError;
use Brahmic\ApiSutra\VO\Errors\ClientErrorFactory;
use Brahmic\ApiSutra\VO\Errors\ErrorContextFactoryInterface;

/**
 * Базовая реализация результата для приложений.
 *
 * Нюансы:
 * - errorViews/errorContexts/continuationToken вычисляются лениво и кэшируются;
 * - continuationToken извлекается extractor-стратегией из сырого ExecutionResult;
 * - errorCode() использует приоритет app -> client -> provider -> sdk.
 *
 * @see docs/guides/errors.md
 * @see docs/guides/continuation-token.md
 */
final readonly class ResolvedResult implements ResolvedResultInterface
{
    private ErrorViewCache $errorViews;
    private ErrorContextCache $errorContexts;
    private ContinuationTokenCache $continuationTokenCache;

    public function __construct(
        private ExecutionResult $result,
        private ClientErrorFactory $errorFactory,
        private ?ErrorContextFactoryInterface $errorContextFactory = null,
        private ?ContinuationTokenExtractorInterface $continuationTokenExtractor = null,
    ) {
        $this->errorViews = new ErrorViewCache();
        $this->errorContexts = new ErrorContextCache();
        $this->continuationTokenCache = new ContinuationTokenCache();
    }

    public function data(): mixed
    {
        return $this->result->data;
    }

    public function isSuccess(): bool
    {
        return $this->result->isSuccess();
    }

    public function isPartial(): bool
    {
        return $this->result->isPartial();
    }

    public function isFailed(): bool
    {
        return $this->result->isFailed();
    }

    public function hasData(): bool
    {
        return $this->result->hasData();
    }

    public function hasErrors(): bool
    {
        return $this->result->hasErrors();
    }

    public function errors(): ErrorCollection
    {
        return $this->result->errors;
    }

    public function errorViews(): array
    {
        return $this->errorViews->get($this->result->errors, $this->errorFactory);
    }

    public function error(): ?ClientError
    {
        $views = $this->errorViews();
        return $views[0] ?? null;
    }

    public function errorContexts(): array
    {
        if ($this->errorContextFactory === null) {
            return [];
        }

        return $this->errorContexts->get($this->errorViews(), $this->errorContextFactory);
    }

    public function errorContext(): ?object
    {
        if ($this->errorContextFactory === null) {
            return null;
        }

        $contexts = $this->errorContexts();
        return $contexts[0] ?? null;
    }

    public function errorCode(): ?string
    {
        $error = $this->error();
        if ($error === null) {
            return null;
        }

        return $error->appCode
            ?? $error->clientCode
            ?? $error->providerCode
            ?? $error->sdkCode->value;
    }

    public function errorMessage(): ?string
    {
        return $this->error()?->message;
    }

    public function errorStatus(): ?int
    {
        return $this->result->errors->first()?->response?->status;
    }

    public function errorRetryable(): ?bool
    {
        return null;
    }

    public function errorCategory(): ?string
    {
        return null;
    }

    public function errorProviderTraceId(): ?string
    {
        $context = $this->errorContext();
        if ($context === null || !property_exists($context, 'providerTraceId')) {
            return null;
        }

        $value = $context->providerTraceId;

        return is_string($value) ? $value : null;
    }

    public function continuationToken(): ?string
    {
        return $this->continuationTokenCache->get(
            result: $this->result,
            extractor: $this->continuationTokenExtractor,
        );
    }

    public function continuationTokenOrFail(): string
    {
        $token = $this->continuationToken();
        if (is_string($token) && $token !== '') {
            return $token;
        }

        throw new SdkException('Continuation token отсутствует в результате');
    }

    public function message(): ?string
    {
        return $this->result->message();
    }

    public function result(): ExecutionResult
    {
        return $this->result;
    }
}
