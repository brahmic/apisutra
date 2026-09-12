<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pipeline\Error;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Core\AbstractClient;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Exceptions\Request\BadGatewayException;
use Brahmic\ApiSutra\Exceptions\Request\ClientException;
use Brahmic\ApiSutra\Exceptions\Request\ServerException;
use Brahmic\ApiSutra\Exceptions\Request\ForbiddenException;
use Brahmic\ApiSutra\Exceptions\Request\GatewayTimeoutException;
use Brahmic\ApiSutra\Exceptions\Request\InternalServerException;
use Brahmic\ApiSutra\Exceptions\Request\NotFoundException;
use Brahmic\ApiSutra\Exceptions\Request\PaymentRequiredException;
use Brahmic\ApiSutra\Exceptions\Request\RateLimitException;
use Brahmic\ApiSutra\Exceptions\Request\RequestException;
use Brahmic\ApiSutra\Exceptions\Request\RequestTimeoutException;
use Brahmic\ApiSutra\Exceptions\Request\ServiceUnavailableException;
use Brahmic\ApiSutra\Exceptions\Request\UnauthorizedException;
use Brahmic\ApiSutra\Exceptions\Request\UnprocessableEntityException;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Throwable;

final readonly class ErrorPolicy
{
    public function __construct(
        private ?AbstractClient $client = null,
    ) {
    }

    public function hasRequestFailed(RequestInterface $request, ?ProviderResponse $response): bool
    {
        if ($response === null) {
            return true;
        }

        if ($request instanceof AbstractRequest && $request->hasRequestFailedInternal($response)) {
            return true;
        }

        if ($this->client !== null) {
            return $this->client->hasRequestFailedInternal($response);
        }

        return $this->hasRequestFailedInternal($response);
    }

    private function hasRequestFailedInternal(ProviderResponse $response): bool
    {
        return $response->status >= 400;
    }

    private function shouldRetryInternal(ProviderResponse $response, int $attempt): bool
    {
        return false;
    }

    public function getRequestExceptionInternal(RequestInterface $request, ProviderResponse $response): ?Throwable
    {
        if ($request instanceof AbstractRequest) {
            $custom = $request->getRequestExceptionInternal($response);
            if ($custom !== null) {
                return $custom;
            }
        }

        if ($this->client !== null) {
            $custom = $this->client->getRequestExceptionInternal($response);
            if ($custom !== null) {
                return $custom;
            }
        }

        return $this->mapException($response);
    }

    private function mapException(ProviderResponse $response): ?RequestException
    {
        $message = $response->errorMessage();

        return match (true) {
            $response->status === 401 => new UnauthorizedException($message, $response),
            $response->status === 402 => new PaymentRequiredException($message, $response),
            $response->status === 403 => new ForbiddenException($message, $response),
            $response->status === 404 => new NotFoundException($message, $response),
            $response->status === 408 => new RequestTimeoutException($message, $response),
            $response->status === 422 => new UnprocessableEntityException($message, $response),
            $response->status === 429 => new RateLimitException(
                $message,
                $response,
                $this->retryAfter($response),
            ),
            $response->status === 500 => new InternalServerException($message, $response),
            $response->status === 502 => new BadGatewayException($message, $response),
            $response->status === 503 => new ServiceUnavailableException($message, $response),
            $response->status === 504 => new GatewayTimeoutException($message, $response),
            $response->status >= 400 && $response->status < 500 => new ClientException($message, $response),
            $response->status >= 500 => new ServerException($message, $response),
            default => null,
        };
    }

    private function retryAfter(ProviderResponse $response): ?int
    {
        $header = $response->header('Retry-After');
        return $header !== null ? (int) $header : null;
    }
}
