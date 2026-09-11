# План рефакторинга AbstractRequest (без изменения бизнес‑логики)

## 1) Цель
Упростить `AbstractRequest`, убрать дублирование и повысить читаемость без изменения поведения и публичного API.

## 2) Границы (что не делаем)
- Не меняем сигнатуры публичных методов.
- Не меняем semantics `send()/sendAsync()/resolved()/dataOrFail()`.
- Не трогаем логику пайплайна и DTO‑гидрации.
- Не меняем механизм атрибутов и RequestSpec.

## 3) Критерии успеха
- Все текущие тесты проходят.
- Сброс runtime‑кэшей централизован и неизменён по смыслу.
- DI‑резолвинг клиента не меняется по поведению.
- Поведение `spec()`/`options()`/`paginationHelper()` сохранено.

## 4) Инварианты (не сломать)
- `resolveClient()` по‑прежнему валидирует ownership.
- `setClient()`/`setContext()`/`__clone()` очищают те же кэши.
- `specResolver` использует metadata cache клиента, если доступно.
- `beforeHydrate()` возвращает payload `response->json()` как раньше.

## 5) Декомпозиция
### Кандидаты на выделение:
1. **RuntimeCaches** — единый helper `resetRuntimeCaches()`.
2. **SpecAccessorsTrait** — getters для spec‑атрибутов.
3. **OptionsAccessorsTrait** — getters для override‑опций.
4. **ClientResolverBridge** — изоляция `app()`‑логики для DI.
5. **HookBridgeTrait** — `before*/after*` + internal‑мосты.

## 6) Последовательность реализации
### Шаг 1 — Централизовать сброс кэшей
- Вынести повторяющиеся сбросы в `resetRuntimeCaches()`.
- Вызвать его в `setClient()`, `setContext()`, `__clone()`.

### Шаг 2 — Разделить accessor‑методы
- Вынести spec‑методы в trait `RequestSpecAccessorsTrait`.
- Вынести option‑методы в trait `RequestOptionsAccessorsTrait`.
- Класс остаётся «сборочным» для trait‑ов.

### Шаг 3 — Изолировать DI‑резолвер
- Вынести `resolveClientResolver()` в отдельный private helper/trait.
- Логика `app()` остаётся прежней.

### Шаг 4 — Hook‑мосты
- Вынести `before*/after*` internal‑мосты в `RequestHooksBridgeTrait`.
- Поведение hook‑потока неизменно.

## 7) Риски
- Случайная смена порядка сброса кэшей.
- Потеря lazy‑инициализации `specResolver`/`options`.
- Ошибка в порядке trait‑методов (если есть конфликты имён).

## 8) Тестирование
- Запуск существующих `Unit/Request/*` + `Unit/Pipeline/*`.
- Отдельно проверить:
  - `setClient()` сбрасывает `spec/specResolver/paginationHelper`.
  - `__clone()` сбрасывает те же кэши.

## 9) Критерии приёмки
- Поведение не изменилось.
- API не ломается.
- Код стал проще и менее связанным.
