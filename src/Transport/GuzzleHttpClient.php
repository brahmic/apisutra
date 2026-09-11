<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Transport;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\HttpClientOptionsInterface;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\VO\Http\TransportOptions;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** Штатный адаптер с известным cURL handler; Guzzle остаётся опциональной зависимостью. */
final readonly class GuzzleHttpClient implements ClientInterface, HttpClientOptionsInterface
{
    private Client $client;

    /** @param array<string, mixed> $config Настройки клиента без подмены HTTP handler. */
    public function __construct(array $config = [])
    {
        if (!class_exists(Client::class) || !extension_loaded('curl')) {
            throw new ConfigurationException('Штатный HTTP-адаптер требует guzzlehttp/guzzle и ext-curl');
        }
        if (array_key_exists('handler', $config)) {
            throw new ConfigurationException('Для собственного Guzzle handler используйте адаптер HttpClientOptionsInterface с явной поддержкой таймаутов');
        }
        $config['handler'] = HandlerStack::create(new CurlHandler());
        $this->client = new Client($config);
    }

    public function assertSupportsTimeouts(TransportOptions $options): void
    {
        // cURL применяет оба таймаута с точностью до миллисекунды.
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->client->sendRequest($request);
    }

    public function sendWithOptions(RequestInterface $request, TransportOptions $options): ResponseInterface
    {
        $effective = $options->effective();
        return $this->client->send($request, [
            'timeout' => $effective->timeoutMs / 1000,
            'connect_timeout' => $effective->connectTimeoutMs / 1000,
            'http_errors' => false,
            'allow_redirects' => false,
        ]);
    }
}
