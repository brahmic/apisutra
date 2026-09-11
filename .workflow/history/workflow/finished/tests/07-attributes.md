# Attributes — план тестирования

## Scope
- `AttributeRegistry`, `AttributeMetadataCache`
- `AttributeContext`, `AttributeContextType`
- `StageProcessor`, атрибуты Request/DTO/Hook

## Invariants
- Кеш метаданных отключён в Local/Testing.
- Атрибуты класса и свойств обрабатываются в порядке объявления.
- Handler может быть `AttributeHandlerInterface` или `AttributeContextHandlerInterface`.

## Узкие места и целевые тесты
- `AttributeContext` должен содержать class attributes и PipelineContext.
- Обработка атрибутов DTO не должна зависеть от pipeline‑контекста.
- Кеш метаданных общий для Serializer/Hydrator/Registry — риск рассинхронизации.

## Сценарии/fixtures (P0)
- **Кеш по окружениям**:
  - Вход: `Environment::Testing` vs `Production`.
  - Ожидание: в Testing cache disabled, в Production — enabled.
  - Fixture: test client.
- **AttributeContext содержит classAttributes**:
  - Вход: класс с 2 атрибутами.
  - Ожидание: classAttributes доступны в контексте обработчика.
  - Fixture: test attribute/handler.
- **DTO‑атрибуты без pipeline**:
  - Вход: Hydrator без `PipelineContext`.
  - Ожидание: DTO‑атрибуты работают.
  - Fixture: test DTO.

## Unit tests
- `AttributeMetadataCache`: enabled/disabled, warmup.
- `AttributeRegistry::getHandler` с resolver и instance.
- `AttributeContext` формируется корректно.

## Integration tests
- Обработка custom attribute на Request и DTO.
- Attribute cache использован в Serializer/Hydrator.

## Edge cases
- Атрибут без зарегистрированного handler → пропуск.
- Некорректный handler → игнор.

## Fixtures/Mocks
- Тестовый attribute + handler.
- Класс с атрибутами на class/property.

## Priority
- P0: кеш по окружениям.
- P1: порядок обработки.
