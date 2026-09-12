<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Transport;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\DestinationAwareInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\FileStreamingInterface;
use Brahmic\ApiSutra\VO\Files\FileTransferOptions;
use Brahmic\ApiSutra\Http\RequestDestination;
use Brahmic\ApiSutra\Http\Origin;
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
final readonly class GuzzleHttpClient implements ClientInterface, HttpClientOptionsInterface, DestinationAwareInterface, FileStreamingInterface
{
    public function assertSupportsFileTransfer(FileTransferOptions $options): void
    {
        if ($this->customCurl) {
            throw new ConfigurationException('Низкоуровневые cURL overrides несовместимы с потоковыми файлами');
        }
    }

    public function assertSupportsDestination(RequestDestination $destination): void
    {
        if ($this->customCurl) {
            throw new ConfigurationException('Низкоуровневые cURL overrides несовместимы с изоляцией назначения');
        }
    }

    private Client $client;
    private Client $isolatedClient;
    private bool $customCurl;

    /** @param array<string, mixed> $config Настройки клиента без подмены HTTP handler. */
    public function __construct(array $config = [])
    {
        if (!class_exists(Client::class) || !extension_loaded('curl')) {
            throw new ConfigurationException('Штатный HTTP-адаптер требует guzzlehttp/guzzle и ext-curl');
        }
        if (array_key_exists('handler', $config)) {
            throw new ConfigurationException('Для собственного Guzzle handler используйте адаптер HttpClientOptionsInterface с явной поддержкой таймаутов');
        }
        $this->customCurl = ($config['curl'] ?? []) !== [];
        // Отдельный клиент не наследует auth, cookies, сертификат клиента и payload defaults.
        $isolated = array_intersect_key($config, array_flip(['verify', 'proxy', 'force_ip_resolve', 'version', 'timeout', 'connect_timeout', 'read_timeout']));
        $isolated['handler'] = HandlerStack::create(new CurlHandler(['handle_factory' => new ExactTargetCurlFactory()]));
        $this->isolatedClient = new Client($isolated);
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
        $destination = $effective->destination;
        $fileOptions = [];
        if ($effective->fileTransfer !== null) {
            $this->assertSupportsFileTransfer($effective->fileTransfer);
            $fileOptions = [
                'sink' => $effective->sink,
                'debug' => false,
                'body' => null,
                'json' => null,
                'form_params' => null,
                'multipart' => null,
            ];
            if ($effective->fileTransfer->download && $effective->sink === null) {
                throw new ConfigurationException('Потоковый download требует sink');
            }
        }
        if ($destination?->requiresIsolation()) {
            $this->assertSupportsDestination($destination);
            if (
                Origin::fromUrl((string) $request->getUri()) !== $destination->origin
                || ($destination->preserveUrl && $request->getRequestTarget() !== $destination->requestTarget())
            ) {
                throw new ConfigurationException('HTTP-клиент получил изменённое назначение запроса');
            }
            return $this->isolatedClient->send($request, [
                'timeout' => $effective->timeoutMs / 1000,
                'connect_timeout' => $effective->connectTimeoutMs / 1000,
                'http_errors' => false,
                'allow_redirects' => false,
                'cookies' => false,
            ] + $fileOptions);
        }
        return $this->client->send($request, [
            'timeout' => $effective->timeoutMs / 1000,
            'connect_timeout' => $effective->connectTimeoutMs / 1000,
            'http_errors' => false,
            'allow_redirects' => false,
        ] + $fileOptions);
    }
}
