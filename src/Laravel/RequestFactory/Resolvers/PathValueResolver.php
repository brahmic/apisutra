<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Laravel\RequestFactory\Resolvers;

use Brahmic\ApiSutra\Laravel\RequestFactory\PayloadKeys;
use Brahmic\ApiSutra\Laravel\RequestFactory\ResolveContext;
use Brahmic\ApiSutra\Laravel\RequestFactory\ResolverInterface;

/**
 * Резолвер значений из параметров пути.
 */
final class PathValueResolver implements ResolverInterface
{
    /**
     * Проверяет, что свойство относится к параметрам пути.
     */
    public function supports(ResolveContext $context): bool
    {
        return $context->pathAttribute !== null
            || in_array($context->propertyName, $context->placeholders, true);
    }

    /**
     * Возвращает значение параметра пути.
     */
    public function resolve(ResolveContext $context): mixed
    {
        $paramName = $context->pathAttribute->name ?? $context->propertyName;

        return $context->payload[PayloadKeys::ROUTE][$paramName] ?? null;
    }
}
