## Цель
Без изменения бизнес‑логики привести `RequestFactory` к более чистой архитектуре, уменьшив количество ветвлений и повысив читаемость, используя возможности PHP 8.4.

## Контекст
Файл: `packages/brahmic/apisutra/src/Laravel/RequestFactory.php`  
Зона ответственности: сбор данных из источника (`Request|array`), маппинг на свойства DTO запроса, учёт атрибутов `Path/Query/Body/Header/File/Ignore`.

## Инварианты (нельзя менять)
- Приоритет источников значений в `collectValues`:
  1) `File` → 2) `Header` → 3) `Path` → 4) `Query` (или query‑метод) → 5) `Body.nested` → 6) `Body`
- `Ignore` исключает свойство полностью.
- `Path` берётся из `payload['route']`, но если плейсхолдер присутствует в URL, он тоже считается `Path`.
- Для `GET/DELETE` в `normalizeSource` тело = query, для остальных тело = input.
- Для массива входа без структуры значения идут и в `query`, и в `body` (как было).
- Заголовки читаются case‑insensitive, берётся только первое значение.
- `resolveMethodAndEndpoint` (или эквивалент) должен иметь дефолт `GET` и возвращать пустой путь, если атрибут не найден.
- `AbstractRequest` заполняется отражением только для НЕ readonly свойств.

## Архитектурное решение
Цепочка резолверов (Chain of Responsibility) + контекст‑DTO:
- `ResolveContext` — readonly DTO с:
  - `ReflectionProperty $property`
  - `array $payload`
  - `array $placeholders`
  - `bool $isQueryMethod`
  - извлечённые атрибуты (`Path/Query/Body/Header/File`)
  - `string $propertyName`
- `RequestDescriptor` — VO для `method` и `path` эндпоинта.
- `ResolverInterface`:
  - `supports(ResolveContext $ctx): bool`
  - `resolve(ResolveContext $ctx): mixed`
- Резолверы:
  - `FileValueResolver`
  - `HeaderValueResolver`
  - `PathValueResolver`
  - `QueryValueResolver`
  - `BodyNestedValueResolver`
  - `BodyValueResolver`
- Порядок резолверов фиксированный и соответствует текущей логике.

## Размещение файлов
`packages/brahmic/apisutra/src/Laravel/RequestFactory/`  
`packages/brahmic/apisutra/src/Laravel/RequestFactory/Resolvers/`

## План работ
1) Создать `ResolveContext` (readonly класс) и `ResolverInterface`.
2) Вынести логику каждого источника в отдельный резолвер:
   - Условия `supports` и извлечение `resolve` строго повторяют текущие ветки.
3) В `RequestFactory::collectValues`:
   - Извлекать атрибуты один раз.
   - Создавать `ResolveContext` с `isQueryMethod`.
   - Прогонять цепочку резолверов, брать первый `supports`.
4) Проверить, что `collectValues` вернёт те же значения на всех путях.
5) Опционально: заменить FQN `#[\Override]` на импорт `use Override;`.

## Точки внимания и нюансы
- `File` резолвер обязан поддерживать `FileInput|array|null` как сейчас.
- `Header` резолвер использует case‑insensitive сравнение ключей.
- `Path` резолвер учитывает `Path::name` и placeholder‑имя.
- `Query` резолвер должен учитывать query‑методы даже без `Query` атрибута.
- `BodyNested` резолвер — только при наличии `Body::nested`.
- `Body` резолвер всегда последний, берёт по имени свойства.

## Критерии приемки
- Все публичные сигнатуры остаются прежними.
- Нет новых побочных эффектов.
- Результаты для одинакового входа идентичны текущим.
- Покрытие тестами не падает.

## Проверка
- Прогнать существующие тесты пакета (минимум `tests/Feature` и `tests/Integration`).
- Проверить несколько ручных кейсов:
  - `GET` с query → тело из query.
  - `POST` с nested body.
  - Файлы: одиночный и массив.

## Риски и меры
- Риск: случайно поменять приоритет ветвлений → фиксируем порядок резолверов.
- Риск: неверная обработка `Path` и плейсхолдеров → отдельные тест‑кейсы.
- Риск: потеря логики `Header` case‑insensitive → вынести в резолвер без изменений.
