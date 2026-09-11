<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Laravel\RequestFactory\Resolvers;

use Brahmic\ApiSutra\Laravel\RequestFactory\PayloadKeys;
use Brahmic\ApiSutra\Laravel\RequestFactory\ResolveContext;
use Brahmic\ApiSutra\Laravel\RequestFactory\ResolverInterface;

/**
 * Резолвер значений из тела запроса.
 */
final class BodyValueResolver implements ResolverInterface
{
    /**
     * Подходит всегда как последний резолвер.
     */
    public function supports(ResolveContext $context): bool
    {
        return true;
    }

    /**
     * Возвращает значение поля из тела запроса.
     */
    public function resolve(ResolveContext $context): mixed
    {
        return $context->payload[PayloadKeys::BODY][$context->propertyName] ?? null;
    }
}
