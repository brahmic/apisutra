# Cache & Idempotency — план тестирования

## Scope
- `CacheManager`, `CacheConfig`, cache key builder
- `RequestOptions::withCache/withoutCache`
- Idempotency header logic (`withIdempotencyKey`, `Idempotent` attr)

## Invariants
- `withoutCache()` = refresh‑mode: чтение off, запись on.
- Ключ кеша учитывает method + baseUrl + query + body.
- `#[Cache(key=...)]` имеет приоритет.

## Узкие места и целевые тесты
- Семантика refresh‑mode должна оставаться неизменной (не отключать запись).
- Стабильность cache key при query/body в разных порядках.
- Взаимодействие кеша и пагинации: page/limit должны попадать в ключ.

## Сценарии/fixtures (P0)
- **Refresh‑mode**:
  - Вход: `withoutCache()` и два вызова одного запроса.
  - Ожидание: чтение пропущено, запись выполнена (кеш пополнен).
  - Fixture: in‑memory PSR‑16 cache + MockTransport.
- **Cache key с query/body**:
  - Вход: одинаковые параметры в разном порядке.
  - Ожидание: ключ одинаковый (нормализация query).
  - Fixture: не требуется (unit).
- **Cache + Pagination**:
  - Вход: `withCache(300)->withPage(2)->withLimit(10)`.
  - Ожидание: page/limit влияют на cache key.
  - Fixture: paginable‑заглушка.

## Unit tests
- `CacheManager::isReadEnabled`/`isWriteEnabled` с override.
- Ключ кеша стабилен при одинаковых входных параметрах.
- `Idempotency-Key` устанавливается из override или генерируется.

## Integration tests
- **Пункт 2.2**: `withCache(300)->withPage(2)` сохраняет `RequestOptions` и `PaginationOptions`,
  а pipeline использует оба (query содержит page/limit, кеш‑ключ корректен).
- `clearCache()` удаляет запись по ожидаемому ключу.

## Edge cases
- Пустой body/query — ключ корректный.
- `CacheConfig` отсутствует → кеш пропускается.

## Fixtures/Mocks
- `MockTransport` и тестовый cache store (PSR‑16 in‑memory).
- Простая paginable‑заглушка.

## Priority
- P0: семантика `withoutCache()` и ключ кеша.
- P1: цепочка `withCache()->withPage()`.
