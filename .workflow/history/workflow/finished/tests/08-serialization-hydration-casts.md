# Serialization / Hydration / Casts — план тестирования

## Scope
- `Serializer`, `Hydrator`
- `CastRegistry` и встроенные касты
- Атрибуты `#[From]`, `#[Cast]`, `#[Nested]`

## Invariants
- NamingStrategy из ClientConfig влияет на сериализацию/гидрацию.
- `serializeNulls` соблюдается.
- `computed()` вызывается до создания DTO.

## Узкие места и целевые тесты
- `Serializer` должен учитывать overrides (baseUrl/headers) и pagination overrides.
- `Hydrator` должен использовать общий `AttributeMetadataCache` клиента.
- `QueryArrayFormat` влияет на сериализацию массивов в query.

## Сценарии/fixtures (P0)
- **Serializer + pagination overrides**:
  - Вход: `withPage(2)->withLimit(10)` и query‑поля.
  - Ожидание: query содержит page/limit из `PaginationOptions`.
  - Fixture: simple request stub.
- **Hydrator cache binding**:
  - Вход: клиент с включённым кешем.
  - Ожидание: повторная гидрация не вызывает повторный scan.
  - Fixture: DTO с атрибутами.

## Unit tests
- `Serializer` применяет `QueryArrayFormat`.
- `Hydrator` вызывает `computed()` и `#[From]` mapping.
- `CastRegistry` выбирает корректный cast по типу.

## Integration tests
- DTO с `#[Nested]` массивом объектов.
- DTO с явными кастами и enum‑кастом.
- `serializeNulls = true/false` в query/body.

## Edge cases
- Некорректный cast → исключение/ошибка.
- Пустой ответ → DTO с nullable полями.

## Fixtures/Mocks
- Пример DTO + nested DTO.
- Ответы JSON с разными форматами.

## Priority
- P0: `computed()` + mapping.
- P1: nested + casts.
