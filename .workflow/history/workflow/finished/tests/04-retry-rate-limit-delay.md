# Retry / RateLimit / Delay — план тестирования

## Scope
- `RetrySender`, `RetryConfigResolver`, `RetryDecisionMaker`
- `RateLimitApplier`, `RateLimiter`
- `DelayApplier`, `RetryHandler`

## Invariants
- `authRetryOn401` отдельный механизм, не включён в общий retry.
- `Retry-After` учитывается для 429.
- Единицы `delay` — миллисекунды.

## Узкие места и целевые тесты
- 401‑flow: гарантировать, что auth‑retry не «съедает» общий retry и не уходит в бесконечный цикл.
- Override‑порядок для retry: disabled/attempts должны работать при отсутствии базового `RetryConfig`.
- RateLimit key должен быть детерминированным и уникальным по `baseUrl`.
- Delay override не должен конфликтовать с config/request.

## Сценарии/fixtures (P0)
- **401 → refresh → retry**:
  - Вход: последовательность ответов 401 → 200.
  - Ожидание: ровно один refresh, один retry, итог SUCCESS.
  - Fixture: MockResponse::sequence.
- **Retry override без базового RetryConfig**:
  - Вход: config без retry, runtime `withRetry(5)`.
  - Ожидание: retries включены с attempts=5.
  - Fixture: MockTransport.
- **Rate limit key**:
  - Вход: два клиента с разным `baseUrl`.
  - Ожидание: ключи различаются; при одинаковом baseUrl — равны.
  - Fixture: stub RateLimiter.

## Unit tests
- `RetryConfigResolver`: порядок override/attribute/config.
- `RetryDecisionMaker::shouldRetry` по статусам/исключениям.
- `DelayApplier` применяет override > request > config.

## Integration tests
- Повтор при 5xx до `attempts`, затем успешный.
- 401 → refresh token → повтор запроса.
- **Пункт 2.3**: rate limit key стабилен и уникален для разных `baseUrl`.

## Edge cases
- Retry disabled override → retries выключены.
- `RetryableException` с maxAttempts.

## Fixtures/Mocks
- `MockTransport` с sequence (500 → 200).
- Stub `RateLimiter` для фиксации key.

## Priority
- P0: 401 flow, retry attempts, rate‑limit key.
- P1: delay override.
