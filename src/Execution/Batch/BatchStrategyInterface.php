<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Execution\Batch;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Throwable;

/**
 * Контракт стратегии batch выполнения.
 */
interface BatchStrategyInterface
{
    /**
     * @param array<int, RequestInterface> $requests
     * @param callable(RequestInterface, Throwable): ExecutionResult $exceptionBuilder
     * @return array<int, ExecutionResult>
     */
    public function execute(BatchContext $context, array $requests, callable $exceptionBuilder): array;
}
