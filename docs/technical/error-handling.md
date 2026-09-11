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

## Переопределения
`AbstractRequest` и `AbstractClient` могут переопределять:
- `hasRequestFailed()`
- `shouldRetry()`
- `getRequestException()`

## Где подробности
- Типы ошибок и исключений: `docs/glossary/results.md`
- Retry/RateLimit: `docs/glossary/execution.md`
- Гайд по ошибкам: `docs/guides/errors.md`
