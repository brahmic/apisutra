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

## Совместный учёт квот

Общая квота клиента и собственная квота операции разрешаются одним набором перед
каждой HTTP-попыткой. RateLimiter организует Wait/Throw и общий budget; backend
только атомарно принимает или отклоняет набор. По умолчанию состояние локальное
и окна измеряются monotonic clock. PSR-16 сохранён только для одной квоты.
[Контракт](../guides/client-config/rate-limit.md), [Redis](../guides/redis-rate-limit.md).
