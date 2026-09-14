# Caching & Retry (обзор)

Короткий обзор кеширования и повторных попыток.

## Кеширование
- Кеш включается через `CacheConfig` в `ClientConfig`.
- Поддерживаются режимы: Enabled / Disabled / ReadOnly / WriteOnly.
- Ключ строится из подготовленного запроса и области кеша; traceId не разделяет записи.
  [Контракт ключа](../guides/client-config/cache.md).
- Сохраняется HTTP-ответ, а не DTO. На cache hit клиент применяет собственный гидратор
  и текущий набор правил; набор не входит в ключ HTTP cache.

Отдельный [кеш метаданных](attributes.md#резолв-и-кеш) ускоряет гидратацию и
сериализацию, сохраняя изоляцию объектных defaults и аргументов атрибутов.

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
