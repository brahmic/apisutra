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
use Brahmic\ApiSutra\Contracts\Interfaces\Core\FileStreamingInterface;
use Brahmic\ApiSutra\VO\Files\FileTransferOptions;
use Brahmic\ApiSutra\Files\StreamCopy;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;

final class SequenceHttpClient implements ClientInterface, HttpClientOptionsInterface, FileStreamingInterface
{
    /** @var list<TransportOptions> */
    public array $options = [];

    public function assertSupportsFileTransfer(FileTransferOptions $options): void
    {
        if ($options->upload) {
            throw new ConfigurationException('SequenceHttpClient поддерживает только download');
        }
    }

    public function assertSupportsTimeouts(TransportOptions $options): void {}

    public function sendWithOptions(RequestInterface $request, TransportOptions $options): ResponseInterface
    {
        $this->options[] = $options->effective();
        $response = $this->sendRequest($request);
        if ($options->sink !== null) {
            StreamCopy::copy($response->getBody(), $options->sink, $options->budget);
            return $response->withBody($options->sink);
        }
        return $response;
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
