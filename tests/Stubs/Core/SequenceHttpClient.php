<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Core;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\HttpClientOptionsInterface;
use Brahmic\ApiSutra\VO\Http\TransportOptions;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

final class SequenceHttpClient implements ClientInterface, HttpClientOptionsInterface
{
    /** @var list<TransportOptions> */
    public array $options = [];

    public function assertSupportsTimeouts(TransportOptions $options): void {}

    public function sendWithOptions(RequestInterface $request, TransportOptions $options): ResponseInterface
    {
        $this->options[] = $options->effective();
        return $this->sendRequest($request);
    }

    public int $calls = 0;

    /** @param list<ResponseInterface|Throwable> $results */
    public function __construct(private array $results) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $result = $this->results[$this->calls++] ?? throw new RuntimeException('Неожиданный HTTP-вызов');
        if ($result instanceof Throwable) {
            throw $result;
        }
        return $result;
    }
}
