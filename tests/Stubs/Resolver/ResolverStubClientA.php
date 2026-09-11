<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Resolver;

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Continuation\ContinuationService;
use RuntimeException;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Enums\Execution\SendMode;
use Brahmic\ApiSutra\Result\ResolvedResultInterface;
use Brahmic\ApiSutra\Result\ResultHandle;
use Brahmic\ApiSutra\Response\ClientResponse;

final class ResolverStubClientA implements ClientInterface
{
    public function __construct(
        private ClientConfig $config,
    ) {}

    public function send(RequestInterface $request, SendMode $mode = SendMode::Sync): ResultHandle
    {
        throw new RuntimeException('Отправка не используется в тестах резолвера.');
    }

    public function sendAsync(RequestInterface $request): ResultHandle
    {
        throw new RuntimeException('Отправка не используется в тестах резолвера.');
    }

    public function response(ResolvedResultInterface $result): ClientResponse
    {
        throw new RuntimeException('Ответ не используется в тестах резолвера.');
    }

    public function getConfig(): ClientConfig
    {
        return $this->config;
    }

    public function continuation(): ContinuationService
    {
        throw new RuntimeException('Continuation не используется в тестах резолвера.');
    }
}
