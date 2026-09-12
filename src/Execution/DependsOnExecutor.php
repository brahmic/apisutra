<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Execution;

use Brahmic\ApiSutra\Collections\RequestCollection;
use Brahmic\ApiSutra\Collections\ResultCollection;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use Brahmic\ApiSutra\Enums\Execution\ExecutionMode;
use Brahmic\ApiSutra\Enums\Execution\FailStrategy;
use Brahmic\ApiSutra\Enums\Execution\RequestRole;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

/**
 * Выполняет зависимости в строгом порядке (sequential).
 *
 * Нюансы:
 * - Зависимости часто требуют последовательности, поэтому Parallel запрещён.
 * - Для параллельного выполнения используйте Composite/Batch.
 * - В будущем, при подтверждённой безопасной семантике, можно реализовать Parallel.
 */
final class DependsOnExecutor
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestCollection $requests,
        private readonly ?PipelineContext $parent = null,
    ) {
    }

    public function execute(ExecutionMode $mode, FailStrategy $strategy): ResultCollection
    {
        if ($mode !== ExecutionMode::Sequential) {
            // Решение: сохраняем API, но запрещаем Parallel для безопасности зависимостей.
            throw new ConfigurationException('DependsOnExecutor поддерживает только Sequential');
        }
        $mode = ExecutionMode::Sequential;
        $batch = new BatchExecutor(
            client: $this->client,
            requests: $this->requests,
            mode: $mode,
            failStrategy: $strategy,
            parent: $this->parent,
            role: RequestRole::Dependency,
        );

        return ResultCollection::make($batch->execute());
    }
}
