# План: typed‑коллекции в apisutra

## Цель
Добавить стабильный typed‑контейнер для коллекций DTO/enum, с runtime‑guard,
интеграцией с `#[Nested]` и базовыми методами для DX, без лишней магии.

## Решения (принятые)
1) **Typed‑only**: коллекции для объектов/enum; скаляры не поддерживаем в typed‑коллекциях.  
2) `map()` **сохраняет тип** и возвращает `static`; для произвольных типов будет `mapToArray()`.  
3) Нарушение guard → `ConfigurationException` (ошибка контракта/инварианта).

## Предлагаемый API (DX)
- `AbstractTypedCollection<T>` (base)  
  - `all(): list<T>`  
  - `first(): ?T`  
  - `count(): int`, `isEmpty(): bool`  
  - `getIterator(): Traversable`  
  - `map(callable(T): T): static`  
  - `filter(callable(T): bool): static`  
  - `fromArray(array $items): static`  
  - `toArray(): array` (глубокий: DTO → `toArray()`, JsonSerializable → `jsonSerialize()`)
  - `protected static function itemClass(): string` — тип элемента
  - guard в конструкторе: каждый элемент `instanceof itemClass()`
- `RawCollection` (escape‑hatch) — без guard, те же базовые методы.

## Изменения в коде
### 1) Core‑коллекции
- `src/Collections/AbstractTypedCollection.php`
- `src/Collections/RawCollection.php`

### 2) Интеграция с гидрацией
- `Hydrator::wrapCollection()`:
  - если у класса есть `fromArray()` — использовать его
  - иначе `new $class($items)`
  - это гарантирует runtime‑guard для typed‑коллекций

### 3) Интеграция с сериализацией
- `DtoSerializer::serializeObject()` уже поддерживает `toArray()` — убедиться, что
  `toArray()` коллекции возвращает сериализованные элементы (DTO → array).

## Тесты (полное покрытие функционала)
### Unit: collections
1) guard: принимает только `itemClass()`  
2) guard: выбрасывает `ConfigurationException` на неверный тип  
3) `all()/first()/count()/isEmpty()`  
4) `map()` возвращает `static` и проверяет тип результата  
5) `filter()` возвращает `static`  
6) `fromArray()` + `toArray()` (DTO/JsonSerializable/обычные объекты)

### Unit: интеграция с Hydrator
7) `#[Nested]` + свойство типа `*Collection` → возвращается typed‑коллекция  
8) неверный элемент в `Nested` → исключение guard

### Unit: интеграция с DtoSerializer
9) коллекция DTO сериализуется в массив через `toArray()`

## Документация
- `docs/glossary/collections.md` — краткое описание typed‑подхода  
- `docs/guides/dto.md` — как объявлять `Collection` в DTO + `#[Nested]`  
- **Новый** `docs/guides/collections.md` — подробный гайд (API, guard, map/filter, RawCollection)  
- `docs/guides/README.md` — добавить ссылку на новый гайд

## Не‑цели v1
- Laravel‑коллекции (допустимо отдельным адаптером/пакетом)
- macros/lazy/pipeline/сложные fluent‑операторы

## Порядок выполнения
1) Core‑классы коллекций  
2) Интеграция в Hydrator  
3) Тесты (collections + integration)  
4) Документация
