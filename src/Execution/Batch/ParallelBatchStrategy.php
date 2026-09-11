<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Execution\Batch;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Enums\Execution\FailStrategy;
use Brahmic\ApiSutra\Enums\Execution\SendMode;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Result\ExecutionResult;
use GuzzleHttp\Promise\Each;
use Throwable;

/**
 * Параллельная стратегия выполнения batch.
 */
final class ParallelBatchStrategy implements BatchStrategyInterface
{
    /**
     * @inheritDoc
     */
    public function execute(BatchContext $context, array $requests, callable $exceptionBuilder): array
    {
        $client = $context->client;
        if ($client === null) {
            throw new ConfigurationException('Не указан клиент для batch выполнения');
        }

        $results = [];
        $shouldStop = false;

        $generator = function () use ($requests, $context, &$shouldStop) {
            foreach ($requests as $index => $request) {
                if ($shouldStop) {
                    break;
                }
                yield $index => $context->send($request, SendMode::Async)->rawAsync();
            }
        };

        Each::ofLimit(
            $generator(),
            $context->concurrency,
            function (ExecutionResult $result, int $index) use (&$results, &$shouldStop, $context): void {
                $results[$index] = $result;
                if ($context->failStrategy === FailStrategy::FailAll && $result->isFailed()) {
                    $shouldStop = true;
                }
            },
            function (mixed $reason, int $index) use (&$results, &$shouldStop, $requests, $context, $exceptionBuilder): void {
                $exception = $reason instanceof Throwable
                    ? $reason
                    : new ConfigurationException('Ошибка выполнения batch запроса');
                $request = $requests[$index] ?? null;
                if ($request instanceof RequestInterface) {
                    $results[$index] = $exceptionBuilder($request, $exception);
                }
                if ($context->failStrategy === FailStrategy::FailAll) {
                    $shouldStop = true;
                }
            },
        )->wait();

        ksort($results);

        return array_values($results);
    }
}
