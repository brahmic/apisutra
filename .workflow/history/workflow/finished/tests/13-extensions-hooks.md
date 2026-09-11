# Extensions & Hooks — план тестирования

## Scope
- `ExtensionRegistry`, `ExtensionInterface`
- `HookRegistry`, `HookRunner`
- Hook attributes (`BeforeSend`, `AfterResponse`, etc.)

## Invariants
- Порядок hook‑вызовов по приоритету.
- Расширения регистрируются/отключаются корректно.

## Unit tests
- `HookRegistry` сортирует по приоритету.
- `ExtensionRegistry` конфликт/disable.

## Integration tests
- Hook‑атрибуты вызываются на нужной стадии.
- Расширение может модифицировать request/response.

## Edge cases
- Несуществующий handler → пропуск.
- Ошибка hook → корректный error flow.

## Fixtures/Mocks
- Заглушка hook handler и extension.

## Priority
- P0: порядок hooks.
