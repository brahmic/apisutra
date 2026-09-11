<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Execution\Batch\Resolvers;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Execution\Batch\BatchContext;
use Brahmic\ApiSutra\Execution\Batch\RequestResolverInterface;

/**
 * Резолвер запросов из callable.
 */
final class CallableRequestResolver implements RequestResolverInterface
{
    public function supports(mixed $item): bool
    {
        return is_callable($item);
    }

    public function resolve(mixed $item, BatchContext $context): ?RequestInterface
    {
        $request = $item($context->parent?->request);

        return $request instanceof RequestInterface ? $request : null;
    }
}
