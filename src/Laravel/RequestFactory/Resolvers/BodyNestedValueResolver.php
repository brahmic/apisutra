<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Laravel\RequestFactory\Resolvers;

use Brahmic\ApiSutra\Laravel\RequestFactory\PayloadKeys;
use Brahmic\ApiSutra\Laravel\RequestFactory\ResolveContext;
use Brahmic\ApiSutra\Laravel\RequestFactory\ResolverInterface;
use Brahmic\ApiSutra\Support\ArrayPath;

/**
 * Резолвер значений из вложенного тела запроса.
 */
final class BodyNestedValueResolver implements ResolverInterface
{
    /**
     * Проверяет, что для свойства задан nested путь.
     */
    public function supports(ResolveContext $context): bool
    {
        return $context->bodyAttribute !== null && $context->bodyAttribute->nested !== null;
    }

    /**
     * Возвращает значение по вложенному пути в теле.
     */
    public function resolve(ResolveContext $context): mixed
    {
        $path = $context->bodyAttribute->nested;
        if ($path === '') {
            return $context->payload[PayloadKeys::BODY][$context->propertyName] ?? null;
        }

        return ArrayPath::getByPath($context->payload[PayloadKeys::BODY] ?? [], $path);
    }
}
