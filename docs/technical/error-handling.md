# Error handling (обзор)

Краткое описание того, как SDK обрабатывает ошибки и формирует результат.

## Где могут возникнуть ошибки
- Валидация запроса (локальная)
- Транспорт (HTTP/сеть)
- Hydration (DTO/формат ответа)
- Бизнес‑ошибки провайдера

## Стратегии
- По умолчанию SDK возвращает `ExecutionResult` со статусом и ошибками.
- При `throwOnErrors = true` — исключение пробрасывается наверх.
- `ClientErrorFactory` + `ClientErrorMapper` отвечают за маппинг ошибок:
  - в `ClientResponseFactory`
  - в DX‑методах `ResolvedResult` (`error()`/`errorViews()`)
- `ErrorContextFactoryInterface` добавляет типизированный контекст для DX (`errorContext()`).
- Системный context ядра: `traceId`, `httpStatus`, `requestClass`, `providerCode`.

Явный `await()` выбрасывает ошибки ожидания независимо от `throwOnErrors`.
`ContinuationAwaitException` сохраняет последний результат и причину ошибки;
неудачная гидратация Ready-payload не превращается в следующий poll.
См. [ожидание и миграцию](../guides/provider-async-await.md).

При внешних правилах HydrationException разделяет DTO-путь и исходный JSON Pointer.
`context()` сохраняет точные данные для результата, `logContext()` маскирует
неизвестные ключи источника в автоматическом логе.
[Границы диагностики](../guides/hydration-rules.md#диагностика-и-входы).

## Переопределения
`AbstractRequest` и `AbstractClient` могут переопределять:
- `hasRequestFailed()`
- `shouldRetry()`
- `getRequestException()`

## Где подробности
- Типы ошибок и исключений: `docs/glossary/results.md`
- Retry/RateLimit: `docs/glossary/execution.md`
- Гайд по ошибкам: `docs/guides/errors.md`
