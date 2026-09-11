# План: глубокая декомпозиция AbstractRequest

## Цель
Разделить ответственность `AbstractRequest` на отдельные компоненты без изменения API.

## Принципы
- Поведение и сигнатуры неизменны.
- Вынесенные части изолированы и тестируемы.

## Декомпозиция
1) `RequestOverrides` (VO/Service)
   - Хранение runtime overrides (cache/retry/auth/timeout/headers)
   - Единые геттеры

2) `RequestAttributeResolver`
   - Извлечение атрибутов (`getCacheAttribute`, `getRetryAttribute`, etc.)
   - Методы `getMethod/getEndpoint/getResponseType`

3) `RequestPaginationHelper`
   - `setPage/setLimit/setCursor`
   - `extractMeta`
   - Работа с pagination defaults

## Нюансы
- Поддержка reflection‑логики должна сохраниться.
- Overrides и pagination должны работать в клонированных экземплярах.

## Шаги реализации
1. Создать вспомогательные классы в `src/Request/`.
2. Перенести логику из `AbstractRequest` в помощники.
3. Подключить помощники через композицию.
4. Удалить дублирующий код в `AbstractRequest`.

## Критерии приёмки
- `AbstractRequest` заметно сокращён.
- Публичные методы работают без изменений.
