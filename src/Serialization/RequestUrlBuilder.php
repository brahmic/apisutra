<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Serialization;

use Brahmic\ApiSutra\Enums\Http\QueryArrayFormat;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final class RequestUrlBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function extractPathParams(string $endpoint): array
    {
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $endpoint, $matches);
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
        $endpoint = $this->applyPathParams($endpoint, $placeholders);
        $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');

        $queryString = $this->buildQueryString(
            $query,
            $context?->config->queryArrayFormat ?? QueryArrayFormat::Brackets,
        );

        if ($queryString !== '') {
            $url .= '?' . $queryString;
        }

        return $url;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function applyPathParams(string $endpoint, array $params): string
    {
        foreach ($params as $name => $value) {
            if ($value === null) {
                continue;
            }
            $endpoint = str_replace('{' . $name . '}', rawurlencode((string) $value), $endpoint);
        }

        return $endpoint;
    }

    /**
     * @param array<string, array{value: mixed, format: ?QueryArrayFormat}> $query
     */
    private function buildQueryString(array $query, QueryArrayFormat $defaultFormat): string
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
                foreach ($this->formatArrayQuery($key, $value, $format) as $pair) {
                    $parts[] = $pair;
                }
                continue;
            }

            $parts[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
        }

        return implode('&', $parts);
    }

    /**
     * @param array<int|string, mixed> $values
     * @return array<int, string>
     */
    private function formatArrayQuery(string $key, array $values, QueryArrayFormat $format): array
    {
        if ($format === QueryArrayFormat::Comma) {
            return [rawurlencode($key) . '=' . rawurlencode(implode(',', $values))];
        }

        $values = array_values($values);
        $indexes = array_keys($values);
        $encodedKey = rawurlencode($key);

        return array_map(
            static fn (int $index, mixed $value): string => match ($format) {
                QueryArrayFormat::Repeat => $encodedKey . '=' . rawurlencode((string) $value),
                QueryArrayFormat::Indices => $encodedKey . '[' . $index . ']=' . rawurlencode((string) $value),
                default => $encodedKey . '[]=' . rawurlencode((string) $value),
            },
            $indexes,
            $values,
        );
    }
}
