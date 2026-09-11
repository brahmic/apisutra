<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Transport;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class HttpTransport implements TransportInterface
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {}

    #[\Override]
    public function send(PreparedRequest $request): ProviderResponse
    {
        $start = microtime(true);
        $psrRequest = $this->buildPsrRequest($request);
        $psrResponse = $this->httpClient->sendRequest($psrRequest);

        return $this->buildProviderResponse($psrResponse, $request, $start);
    }

    #[\Override]
    public function sendAsync(PreparedRequest $request): PromiseInterface
    {
        $promise = new Promise();

        try {
            $promise->resolve($this->send($request));
        } catch (\Throwable $exception) {
            $promise->reject($exception);
        }

        return $promise;
    }

    private function buildPsrRequest(PreparedRequest $request): \Psr\Http\Message\RequestInterface
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
