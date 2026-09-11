<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Execution\Batch;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\ContextualClientInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Enums\Execution\ExecutionMode;
use Brahmic\ApiSutra\Enums\Execution\FailStrategy;
use Brahmic\ApiSutra\Enums\Execution\RequestRole;
use Brahmic\ApiSutra\Enums\Execution\SendMode;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Result\ResultHandle;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

/**
 * Контекст выполнения batch.
 */
readonly class BatchContext
{
    public function __construct(
        public ?ClientInterface $client,
        public ExecutionMode $mode,
        public FailStrategy $failStrategy,
        public int $concurrency,
        public ?PipelineContext $parent,
        public RequestRole $role,
    ) {
    }

    public function send(RequestInterface $request, SendMode $mode = SendMode::Sync): ResultHandle
    {
        if ($this->client === null) {
            throw new ConfigurationException('Не указан клиент для batch выполнения');
        }
        if ($this->parent !== null && $this->client instanceof ContextualClientInterface) {
            return $this->client->sendInContext($request, $this->parent, $this->role, $mode);
        }
        if ($this->parent?->budget?->deadlineMs !== null) {
            throw new ConfigurationException('Клиент вложенного запроса должен поддерживать ContextualClientInterface для наследования deadline');
        }
        return $mode === SendMode::Async
            ? $this->client->sendAsync($request)
            : $this->client->send($request);
    }
}
