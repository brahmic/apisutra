<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Serialization;

use Brahmic\ApiSutra\Enums\Http\QueryArrayFormat;
use Brahmic\ApiSutra\Enums\Serialization\BooleanFormat;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Exceptions\Serialization\SerializationException;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final class RequestUrlBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function extractPathParams(string $endpoint): array
    {
        $this->assertRelativeEndpoint($endpoint);
        [$path] = UrlQuery::split($endpoint);
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $path, $matches);
        $params = [];
        foreach ($matches[1] ?? [] as $name) {
            $params[$name] = null;
        }

        return $params;
    }

    /**
     * @param array<string, mixed> $placeholders
     * @param array<string, array{value: mixed, format: ?QueryArrayFormat}> $query
     */
    public function buildUrl(
        string $baseUrl,
        string $endpoint,
        array $placeholders,
        array $query,
        ?PipelineContext $context,
    ): string {
        $this->assertRelativeEndpoint($endpoint);
        [$basePath, $baseQuery] = UrlQuery::split($baseUrl);
        [$endpointPath, $endpointQuery] = UrlQuery::split($endpoint);
        $endpointPath = $this->applyPathParams($endpointPath, $placeholders);
        $url = rtrim($basePath, '/') . '/' . ltrim($endpointPath, '/');

        return UrlQuery::append($url, $baseQuery, $endpointQuery, $this->buildQueryString(
            $query,
            $context?->config->queryArrayFormat ?? QueryArrayFormat::Brackets,
            $context?->config->textBooleanFormat ?? BooleanFormat::Numeric,
        ));
    }

    private function assertRelativeEndpoint(string $endpoint): void
    {
        $endpoint = explode('#', $endpoint, 2)[0];
        if (
            str_starts_with($endpoint, '//') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $endpoint)
            || preg_match('/[\\x00-\\x20\\x7f]/', $endpoint) || str_contains($endpoint, chr(92))
        ) {
            throw new ConfigurationException('Endpoint должен быть относительным URI без управляющих символов');
        }
        [$path] = UrlQuery::split($endpoint);
        foreach (explode('/', $path) as $segment) {
            if (in_array(rawurldecode($segment), ['.', '..'], true)) {
                throw new ConfigurationException('Сегменты . и .. в endpoint не поддерживаются');
            }
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function applyPathParams(string $endpoint, array $params): string
    {
        $path = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function (array $match) use ($params): string {
            $value = $params[$match[1]] ?? null;
            if ($value === null || !is_scalar($value) || (is_float($value) && !is_finite($value))) {
                throw new SerializationException('Отсутствует или недопустим path-параметр: ' . $match[1]);
            }
            $value = (string) $value;
            if ($value === '' || $value === '.' || $value === '..') {
                throw new SerializationException('Path-параметр не может быть пустым, . или ..: ' . $match[1]);
            }
            return rawurlencode($value);
        }, $endpoint);
        if ($path === null || strpbrk($path, '{}') !== false) {
            throw new SerializationException('Некорректный или незаполненный placeholder в path');
        }
        return $path;
    }

    /**
     * @param array<string, array{value: mixed, format: ?QueryArrayFormat}> $query
     */
    private function buildQueryString(array $query, QueryArrayFormat $defaultFormat, BooleanFormat $booleanFormat): string
    {
        $parts = [];
        foreach ($query as $key => $data) {
            $value = $data['value'] ?? null;
            $format = $data['format'] ?? $defaultFormat;
            if ($value === null) {
                $parts[] = rawurlencode((string) $key) . '=';
                continue;
            }

            if (is_array($value)) {
                foreach ($this->formatArrayQuery((string) $key, $value, $format, $booleanFormat) as $pair) {
                    $parts[] = $pair;
                }
                continue;
            }

            $parts[] = rawurlencode((string) $key) . '=' . rawurlencode($this->queryScalar($value, $booleanFormat));
        }

        return implode('&', $parts);
    }

    /**
     * @param array<int|string, mixed> $values
     * @return array<int, string>
     */
    private function formatArrayQuery(string $key, array $values, QueryArrayFormat $format, BooleanFormat $booleanFormat): array
    {
        if (!array_is_list($values)) {
            throw new SerializationException('Query поддерживает только плоские списки; для структуры используйте явный cast');
        }
        if ($values === []) {
            return [];
        }
        $values = array_map(fn (mixed $value): string => $this->queryScalar($value, $booleanFormat), $values);
        if ($format === QueryArrayFormat::Comma) {
            foreach ($values as $value) {
                if (str_contains($value, ',')) {
                    throw new SerializationException('Значение списка Comma содержит разделитель; используйте другой формат или явный cast');
                }
            }
            return [rawurlencode($key) . '=' . rawurlencode(implode(',', $values))];
        }

        $parts = [];
        foreach ($values as $index => $value) {
            $name = match ($format) {
                QueryArrayFormat::Repeat => $key,
                QueryArrayFormat::Indices => $key . '[' . $index . ']',
                default => $key . '[]',
            };
            // Сохраняем существующий вид скобок для query-списков.
            $encodedKey = rawurlencode($key) . substr($name, strlen($key));
            $parts[] = $encodedKey . '=' . rawurlencode($value);
        }
        return $parts;
    }

    private function queryScalar(mixed $value, BooleanFormat $booleanFormat): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $booleanFormat->format($value),
            is_string($value), is_int($value) => (string) $value,
            is_float($value) && is_finite($value) => (string) $value,
            default => throw new SerializationException('Query ожидает скаляр или плоский список скаляров; используйте явный cast'),
        };
    }
}
