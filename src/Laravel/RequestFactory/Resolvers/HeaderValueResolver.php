<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Laravel\RequestFactory\Resolvers;

use Brahmic\ApiSutra\Laravel\RequestFactory\PayloadKeys;
use Brahmic\ApiSutra\Laravel\RequestFactory\ResolveContext;
use Brahmic\ApiSutra\Laravel\RequestFactory\ResolverInterface;

/**
 * Резолвер значений из заголовков запроса.
 */
final class HeaderValueResolver implements ResolverInterface
{
    /**
     * Проверяет наличие атрибута заголовка.
     */
    public function supports(ResolveContext $context): bool
    {
        return $context->headerAttribute !== null;
    }

    /**
     * Возвращает значение заголовка для свойства.
     */
    public function resolve(ResolveContext $context): ?string
    {
        $headerName = $context->headerAttribute->name;

        return $this->readFromHeaders($context->payload[PayloadKeys::HEADERS] ?? [], $headerName);
    }

    /**
     * Ищет заголовок без учёта регистра и берёт первое значение.
     *
     * @param array<string, mixed> $headers Заголовки запроса.
     */
    private function readFromHeaders(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $values) {
            if (strcasecmp($key, $name) === 0) {
                $first = is_array($values) ? ($values[0] ?? null) : $values;
                return $first !== null ? (string) $first : null;
            }
        }

        return null;
    }
}
