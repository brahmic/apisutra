<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Execution\Batch\Resolvers;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Execution\Batch\BatchContext;
use Brahmic\ApiSutra\Execution\Batch\RequestResolverInterface;

/**
 * Резолвер готовых инстансов запросов.
 */
final class RequestInstanceResolver implements RequestResolverInterface
{
    public function supports(mixed $item): bool
    {
        return $item instanceof RequestInterface;
    }

    public function resolve(mixed $item, BatchContext $context): ?RequestInterface
    {
        return $item;
    }
}
