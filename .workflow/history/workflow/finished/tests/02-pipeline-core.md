# Pipeline Core — план тестирования

## Scope
- `Pipeline`, `PipelineContext`, `PipelineContextFactory`
- `RequestPreparer`, `RequestFlowRunner`, `ExecutionResultBuilder`
- `HookRunner`, `ResponseHydrator`, `ResultFactory`

## Invariants
- Порядок стадий pipeline не меняется.
- `PipelineContext` сохраняет `RequestOptions` и `PaginationOptions`.
- Hooks вызываются на своих стадиях.

## Узкие места и целевые тесты
- Unwrap `RequestExecution` должен сохранять options/paginationOptions в контексте.
- Порядок стадий критичен для cache/retry/hydration (риск регрессии при рефакторинге).
- Hooks не должны менять данные вне своей стадии.

## Сценарии/fixtures (P0)
- **Unwrap RequestExecution**:
  - Вход: `withCache(300)->withPage(2)` и `send()`.
  - Ожидание: `PipelineContext` содержит и `RequestOptions`, и `PaginationOptions`.
  - Fixture: MockTransport с простым ответом.
- **Порядок стадий**:
  - Вход: запрос с hook‑атрибутами (before/after).
  - Ожидание: события в правильном порядке (beforeSend → afterResponse → beforeHydrate → afterHydrate).
  - Fixture: MockTransport, простой DTO.
- **Hydration**:
  - Вход: ответ с nested DTO.
  - Ожидание: DTO создан, `afterHydrate` вызван.
  - Fixture: JSON с nested.

## Unit tests
- `PipelineContextFactory`: корректное формирование `traceId`, `role`, `options`.
- `RequestPreparer`: корректная сборка `PreparedRequest` (url, headers, body).
- `ExecutionResultBuilder`: консистентный статус и мета.

## Integration tests
- Полный pipeline для простого запроса → `ExecutionResult`.
- Hooks: before/after stages вызываются и получают `PipelineContext`.
- Hydration: DTO создаётся, `afterHydrate` вызывается.

## Edge cases
- `RequestInterface` без клиента → ошибка.
- `RequestExecutionInterface` unwrap: options и paginationOptions доступны в контексте.

## Fixtures/Mocks
- `MockTransport` с фиксированными ответами.
- Простой DTO + атрибуты `#[From]`, `#[Cast]`, `#[Nested]`.

## Priority
- P0: порядок стадий + корректный `PipelineContext`.
- P1: hooks + hydration.
