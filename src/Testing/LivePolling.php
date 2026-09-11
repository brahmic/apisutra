<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Testing;

use RuntimeException;

/**
 * Polling-хелпер для async/polling flow в live-тестах.
 *
 * Вызовы внутри callback должны использовать ->withoutCache(),
 * т.к. статус меняется во времени (pending → ready).
 */
final readonly class LivePolling
{
    /**
     * @template T
     * @param callable(): T $fetch
     * @param callable(T): bool $isReady
     * @return T
     */
    public static function waitUntil(
        callable $fetch,
        callable $isReady,
        int $timeoutSeconds = 60,
        int $intervalMilliseconds = 1000,
        ?string $timeoutMessage = null,
    ): mixed {
        $message = $timeoutMessage ?? 'Превышен таймаут ожидания';
        $startedAt = microtime(true);
        $last = null;

        while ((microtime(true) - $startedAt) < $timeoutSeconds) {
            $last = $fetch();
            if ($isReady($last)) {
                return $last;
            }

            usleep($intervalMilliseconds * 1000);
        }

        throw new RuntimeException(self::buildTimeoutMessage(
            timeoutMessage: $message,
            timeoutSeconds: $timeoutSeconds,
            intervalMilliseconds: $intervalMilliseconds,
            lastValue: $last,
        ));
    }

    private static function buildTimeoutMessage(
        string $timeoutMessage,
        int $timeoutSeconds,
        int $intervalMilliseconds,
        mixed $lastValue,
    ): string {
        $base = sprintf(
            '%s (timeout=%ds, interval=%dms)',
            $timeoutMessage,
            $timeoutSeconds,
            $intervalMilliseconds,
        );

        if ($lastValue === null) {
            return $base . ', last: null';
        }

        return $base . ', last: ' . self::describeLastValue($lastValue);
    }

    private static function describeLastValue(mixed $value): string
    {
        if (is_object($value)) {
            $vars = get_object_vars($value);
            $parts = ['class=' . $value::class];

            foreach (['id', 'docflowId', 'monitoringId', 'orderId', 'queryNum', 'taskId'] as $key) {
                if (isset($vars[$key]) && is_scalar($vars[$key])) {
                    $parts[] = sprintf('%s=%s', $key, (string) $vars[$key]);
                    break;
                }
            }

            foreach (['state', 'status', 'docflowState', 'monitoringState'] as $key) {
                if (!isset($vars[$key])) {
                    continue;
                }
                $state = $vars[$key];
                if ($state instanceof \BackedEnum) {
                    $parts[] = sprintf('%s=%s', $key, (string) $state->value);
                } elseif ($state instanceof \UnitEnum) {
                    $parts[] = sprintf('%s=%s', $key, $state->name);
                } elseif (is_scalar($state)) {
                    $parts[] = sprintf('%s=%s', $key, (string) $state);
                }
                break;
            }

            return implode(', ', $parts);
        }

        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return is_string($encoded) ? substr($encoded, 0, 200) . (strlen($encoded) > 200 ? '...' : '') : 'array';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return gettype($value);
    }
}
