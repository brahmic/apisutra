<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Laravel\RequestFactory\Resolvers;

use Brahmic\ApiSutra\Laravel\RequestFactory\PayloadKeys;
use Brahmic\ApiSutra\Laravel\RequestFactory\ResolveContext;
use Brahmic\ApiSutra\Laravel\RequestFactory\ResolverInterface;

/**
 * Резолвер значений из query параметров.
 */
final class QueryValueResolver implements ResolverInterface
{
    /**
     * Проверяет, что свойство читается из query.
     */
    public function supports(ResolveContext $context): bool
    {
        if ($context->bodyAttribute !== null) {
            return false;
        }

        return $context->queryAttribute !== null || $context->isQueryMethod;
    }

    /**
     * Возвращает значение query параметра.
     */
    public function resolve(ResolveContext $context): mixed
    {
        $paramName = $context->queryAttribute->name ?? $context->propertyName;

        return $context->payload[PayloadKeys::QUERY][$paramName] ?? null;
    }
}
