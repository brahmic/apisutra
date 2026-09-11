# Pagination — план тестирования

## Scope
- `Paginator`, `PaginationMeta`, `PaginationOptions`
- `AbstractPaginatedRequest`, `PaginableInterface`
- `RequestPaginationHelper`

## Invariants
- `offsetBased` = false по умолчанию (page‑based).
- `offsetBased: true` требует `limit`.
- `Paginator` — мутабельный builder.

## Узкие места и целевые тесты
- `buildExecution()` должен корректно выбирать путь: `RequestExecution` при options и `AbstractRequest` при null.
- Переопределённые `withPage/withLimit/withCursor` в запросах должны вызываться.
- Извлечение меты: метод `extractMeta()` имеет приоритет над атрибутом.

## Сценарии/fixtures (P0)
- **Default page‑based**:
  - Вход: `paginate()->pages(1)` без `limit`.
  - Ожидание: page=1, параметр `page`.
  - Fixture: mock response с meta/data.
- **Offset‑based без limit**:
  - Вход: `offsetBased: true`, без `withLimit`.
  - Ожидание: `ConfigurationException`.
  - Fixture: не требуется.
- **Override методов with***:
  - Вход: request с переопределённым `withPage()`.
  - Ожидание: метод вызывается (можно фиксировать флагом).
  - Fixture: test request‑stub.

## Unit tests
- `PaginationOptions` флаги `has*` и значения.
- `Paginator::resolvePage` для page‑based и offset‑based.
- `extractMeta()` приоритет над атрибутом.

## Integration tests
- **Пункт 1.1**: page‑based по умолчанию без `limit`.
- **Пункт 1.2**: offset‑based без `limit` → `ConfigurationException`.
- Cursor‑based: `nextCursor` → продолжение.

## Edge cases
- Meta без `total` и без `nextCursor` → `hasMore = false`.
- `perPage` = 0 → корректное поведение.

## Fixtures/Mocks
- Mock responses с `meta` и `data` (page/offset/cursor).

## Priority
- P0: page‑based default, offset‑based require limit.
- P1: cursor‑based.
