<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pipeline\Transport;

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\RetryConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Core\AbstractClient;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Exceptions\ControlFlow\RetryableException;
use Brahmic\ApiSutra\Pipeline\Error\ErrorPolicy;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Throwable;

final readonly class RetryDecisionMaker
{
    public function __construct(
        private ClientConfig $config,
        private ErrorPolicy $errorPolicy,
        private ?AbstractClient $client = null,
    ) {}

    public function shouldRetry(
        RequestInterface $request,
        ProviderResponse $response,
        int $attempt,
        ?RetryConfig $retryConfig,
    ): bool {
        if ($response->status === 401 && $this->config->authRetryOn401) {
            return false;
        }

        if ($request instanceof AbstractRequest && $request->shouldRetryInternal($response, $attempt)) {
            return true;
        }

        if ($this->client?->shouldRetryInternal($response, $attempt) === true) {
            return true;
        }

        $exception = $this->errorPolicy->getRequestExceptionInternal($request, $response);
        if ($exception instanceof RetryableException) {
            return true;
        }

        if ($retryConfig === null) {
            return false;
        }

        return in_array($response->status, $retryConfig->retryOn, true);
    }

    public function isRetryException(Throwable $exception, RetryConfig $retryConfig): bool
    {
        foreach ($retryConfig->retryExceptions as $class) {
            if ($exception instanceof $class) {
                return true;
            }
        }

        return false;
    }
}
