# Results & Collections — план тестирования

## Scope
- `ExecutionResult`, `BatchResult`, `PoolResult`, `PaginatedResult`
- `ResultCollection`, `ErrorCollection`, `ResultSummary`, `BatchMeta`

## Invariants
- `ResultCollection::summarize()` — единая логика статуса.
- `BatchMeta` соответствует `ResultSummary`.

## Узкие места и целевые тесты
- Консистентность статусов между Batch/Pool/Paginator.
- PARTIAL не должен «пропадать» при агрегировании.
- Пустая коллекция — предсказуемый статус.

## Сценарии/fixtures (P0)
- **Смешанные статусы**:
  - Вход: SUCCESS + FAILED → итог PARTIAL.
  - Fixture: набор `ExecutionResult`.
- **Все success**:
  - Вход: 3x SUCCESS → итог SUCCESS.
  - Fixture: набор `ExecutionResult`.
- **Все failed**:
  - Вход: 2x FAILED → итог FAILED.
  - Fixture: набор `ExecutionResult`.

## Unit tests
- `ResultCollection` подсчёты: total/success/failed/partial.
- `resolveStatus()` соответствует `summarize()`.
- `ErrorCollection` агрегирует ошибки.

## Integration tests
- `BatchExecutor`/`PoolExecutor` используют `ResultCollection::summarize()`.
- `Paginator` статус согласован с `ResultSummary`.

## Edge cases
- Пустая коллекция.
- Все failed / все success / смешанные.

## Fixtures/Mocks
- Набор `ExecutionResult` с разными статусами.

## Priority
- P0: статус‑агрегация.
