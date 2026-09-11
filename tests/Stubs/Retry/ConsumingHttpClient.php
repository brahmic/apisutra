<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Retry;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

final class ConsumingHttpClient implements ClientInterface
{
    /** @var list<string> */
    public array $bodies = [];
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @param list<ResponseInterface|Throwable> $results */
    public function __construct(private array $results) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->bodies[] = $request->getBody()->getContents();
        $this->requests[] = $request;
        $result = array_shift($this->results) ?? throw new RuntimeException('Неожиданный HTTP-вызов');
        if ($result instanceof Throwable) {
            throw $result;
        }
        return $result;
    }
}
