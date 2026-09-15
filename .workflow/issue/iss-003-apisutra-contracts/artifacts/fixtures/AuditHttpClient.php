<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Contracts\Interfaces\Core\HttpClientOptionsInterface;
use Brahmic\ApiSutra\VO\Http\TransportOptions;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class AuditHttpClient implements ClientInterface, HttpClientOptionsInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<TransportOptions> */
    public array $options = [];

    /** @param list<ResponseInterface|Throwable> $responses */
    public function __construct(private array $responses) {}

    #[Override]
    public function assertSupportsTimeouts(TransportOptions $options): void {}

    #[Override]
    public function sendWithOptions(RequestInterface $request, TransportOptions $options): ResponseInterface
    {
        $this->options[] = $options;

        return $this->sendRequest($request);
    }

    #[Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $response = array_shift($this->responses) ?? throw new RuntimeException('Unexpected HTTP request.');
        if ($response instanceof Throwable) {
            throw $response;
        }

        return $response;
    }
}
