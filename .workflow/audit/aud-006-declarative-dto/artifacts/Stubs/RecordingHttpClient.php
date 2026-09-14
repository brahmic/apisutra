<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\HttpClientOptionsInterface;
use Brahmic\ApiSutra\VO\Http\TransportOptions;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class RecordingHttpClient implements ClientInterface, HttpClientOptionsInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @param list<ResponseInterface> $responses */
    public function __construct(private array $responses)
    {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        // Незаявленный запрос немедленно завершает сценарий, сеть не используется.
        return array_shift($this->responses) ?? throw new RuntimeException('Unexpected fixture HTTP request');
    }

    public function assertSupportsTimeouts(TransportOptions $options): void
    {
        // Синхронная выдача локального ответа не содержит ожиданий или сетевых операций.
    }

    public function sendWithOptions(RequestInterface $request, TransportOptions $options): ResponseInterface
    {
        return $this->sendRequest($request);
    }
}
