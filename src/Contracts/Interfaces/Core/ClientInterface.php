<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Core;

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Continuation\ContinuationService;
use Brahmic\ApiSutra\Enums\Execution\SendMode;
use Brahmic\ApiSutra\Response\ClientResponse;
use Brahmic\ApiSutra\Result\ResolvedResultInterface;
use Brahmic\ApiSutra\Result\ResultHandle;

interface ClientInterface
{
    /**
     * Выполнить запрос синхронно
     */
    public function send(RequestInterface $request, SendMode $mode = SendMode::Sync): ResultHandle;

    /**
     * Выполнить запрос асинхронно
     */
    public function sendAsync(RequestInterface $request): ResultHandle;

    /**
     * Сформировать клиентский ответ
     */
    public function response(ResolvedResultInterface $result): ClientResponse;

    /**
     * Получить конфигурацию
     */
    public function getConfig(): ClientConfig;

    /**
     * Получить сервис continuation orchestration.
     */
    public function continuation(): ContinuationService;
}
