# Caching & Retry (обзор)

Короткий обзор кеширования и повторных попыток.

## Кеширование
- Кеш включается через `CacheConfig` в `ClientConfig`.
- Поддерживаются режимы: Enabled / Disabled / ReadOnly / WriteOnly.
- Ключи зависят от запроса, параметров и traceId (если задан).

## Retry
- Управляется `RetryConfig` и политикой `shouldRetry()`.
- Поддерживает backoff‑стратегии и jitter.
- Может учитывать `Retry-After`.

## Rate limiting
- Лимиты задаются в `RateLimitConfig`.
- Поведение при превышении определяется `RateLimitBehavior`.

## Где подробности
- Конфигурация: `docs/guides/client-config/README.md`
- Исполнение: `docs/technical/execution.md`
