<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Transport;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\DestinationAwareInterface;
use Brahmic\ApiSutra\Http\RequestDestination;
use Brahmic\ApiSutra\Http\DestinationGuard;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\HttpClientOptionsInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\TimeoutAwareTransportInterface;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Brahmic\ApiSutra\VO\Http\TransportOptions;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\HttpFactory;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Throwable;

final class HttpTransport implements TimeoutAwareTransportInterface, DestinationAwareInterface
{
    public function assertSupportsDestination(RequestDestination $destination): void
    {
        DestinationGuard::checkCapability($this->httpClient, $destination);
    }

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {}

    /** Штатная сборка без ручного выбора адаптера и фабрик. */
    public static function createDefault(): self
    {
        $factory = new HttpFactory();
        return new self(new GuzzleHttpClient(), $factory, $factory);
    }

    public function assertSupportsTimeouts(TransportOptions $options): void
    {
        if ($this->httpClient instanceof HttpClientOptionsInterface) {
            $this->httpClient->assertSupportsTimeouts($options);
        } elseif ($options->hasLimits()) {
            throw new ConfigurationException($this->httpClient::class . ' не поддерживает per-request timeout/connectTimeout/deadline; нужен HttpClientOptionsInterface');
        }
    }

    #[Override]
    public function send(PreparedRequest $request): ProviderResponse
    {
        DestinationGuard::checkRequest($request);
        DestinationGuard::checkCapability($this, $request->destination);
        $start = microtime(true);
        $psrRequest = $this->buildPsrRequest($request);
        $options = $request->transportOptions;
        if ($request->destination !== null) {
            $options = new TransportOptions($options?->timeoutMs ?? 0, $options?->connectTimeoutMs ?? 0, $options?->budget, $request->destination);
        }
        if ($options !== null) {
            $this->assertSupportsTimeouts($options);
            $options = $options->effective();
        }
        try {
            $psrResponse = $options !== null && $this->httpClient instanceof HttpClientOptionsInterface
                ? $this->httpClient->sendWithOptions($psrRequest, $options)
                : $this->httpClient->sendRequest($psrRequest);
        } catch (Throwable $exception) {
            throw TransportExceptionNormalizer::normalize($exception);
        }

        return $this->buildProviderResponse($psrResponse, $request, $start);
    }

    #[Override]
    public function sendAsync(PreparedRequest $request): PromiseInterface
    {
        $promise = new Promise();

        try {
            $promise->resolve($this->send($request));
        } catch (Throwable $exception) {
            $promise->reject($exception);
        }

        return $promise;
    }

    private function buildPsrRequest(PreparedRequest $request): PsrRequestInterface
    {
        $psrRequest = $this->requestFactory->createRequest($request->method->value, $request->url);

        foreach ($request->headers as $name => $value) {
            $psrRequest = $psrRequest->withHeader($name, $value);
        }

        if ($request->stream !== null) {
            $psrRequest = $psrRequest->withBody($request->stream);
        } elseif ($request->body !== null) {
            $psrRequest = $psrRequest->withBody($this->streamFactory->createStream($request->body));
        }

        if ($request->destination?->preserveUrl) {
            $target = $request->destination->requestTarget();
            // Фабрика URI может потерять пустой '?'; request target задаётся отдельно.
            if ($psrRequest->getRequestTarget() !== rtrim($target, '?') && $psrRequest->getRequestTarget() !== $target) {
                throw new ConfigurationException('PSR-фабрика изменяет готовый request target');
            }
            $psrRequest = $psrRequest->withRequestTarget($target);
        }
        return $psrRequest;
    }

    private function buildProviderResponse(
        ResponseInterface $response,
        PreparedRequest $request,
        float $startTime,
    ): ProviderResponse {
        $duration = (microtime(true) - $startTime) * 1000;

        return new ProviderResponse(
            status: $response->getStatusCode(),
            headers: $response->getHeaders(),
            body: (string) $response->getBody(),
            request: $request,
            duration: $duration,
        );
    }
}
