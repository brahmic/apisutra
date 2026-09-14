<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Continuation;

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Continuation\ContinuationService;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Enums\Execution\SendMode;
use Brahmic\ApiSutra\Response\ClientResponse;
use Brahmic\ApiSutra\Result\ResolvedResultInterface;
use Brahmic\ApiSutra\Result\ResultHandle;
use Brahmic\ApiSutra\Serialization\Hydrator;

final readonly class DelegatingClient implements ClientInterface
{
    private ContinuationService $service;

    public function __construct(private ClientInterface $client, Hydrator $hydrator)
    {
        $this->service = new ContinuationService($this, $hydrator);
    }

    public function send(RequestInterface $request, SendMode $mode = SendMode::Sync): ResultHandle
    {
        return $this->client->send($request, $mode);
    }

    public function sendAsync(RequestInterface $request): ResultHandle
    {
        return $this->client->sendAsync($request);
    }

    public function response(ResolvedResultInterface $result): ClientResponse
    {
        return $this->client->response($result);
    }

    public function getConfig(): ClientConfig
    {
        return $this->client->getConfig();
    }

    public function continuation(): ContinuationService
    {
        return $this->service;
    }
}
