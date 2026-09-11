<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Execution\Batch;

use Brahmic\ApiSutra\Enums\Execution\FailStrategy;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;

/**
 * Последовательная стратегия выполнения batch.
 */
final class SequentialBatchStrategy implements BatchStrategyInterface
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
        foreach ($requests as $index => $request) {
            $result = $client->send($request)->raw();
            $results[$index] = $result;

            if ($context->failStrategy === FailStrategy::FailAll && $result->isFailed()) {
                break;
            }
        }

        ksort($results);
        return array_values($results);
    }
}
