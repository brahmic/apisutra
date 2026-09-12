<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Retry;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\HttpClientOptionsInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\DestinationAwareInterface;
use Brahmic\ApiSutra\Http\RequestDestination;
use Brahmic\ApiSutra\VO\Http\TransportOptions;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\FileStreamingInterface;
use Brahmic\ApiSutra\VO\Files\FileTransferOptions;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Throwable;

final class ConsumingHttpClient implements ClientInterface, HttpClientOptionsInterface, DestinationAwareInterface, FileStreamingInterface
{
    public function assertSupportsFileTransfer(FileTransferOptions $options): void
    {
        if ($options->download) {
            throw new ConfigurationException('Тестовый адаптер поддерживает только upload');
        }
    }

    public function assertSupportsDestination(RequestDestination $destination): void {}

    /** @var list<TransportOptions> */
    public array $options = [];

    public function assertSupportsTimeouts(TransportOptions $options): void {}

    public function sendWithOptions(RequestInterface $request, TransportOptions $options): ResponseInterface
    {
        $this->options[] = $options->effective();
        return $this->sendRequest($request);
    }

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
