# План: поддержка item-level cast в `#[Nested]`

## Проблема и ожидания
Сейчас `Nested` решает только структурную задачу:

- указать тип элемента;
- указать путь `from`;
- поддержать `each`;
- поддержать discriminator/polymorphism.

Но `Nested` не умеет преобразовывать **формат значения каждого элемента** перед гидрацией.

Из-за этого не покрывается важный DX-кейс:

- provider присылает `list<data-uri-string>`
- SDK хочет получить `list<Base64File>`

Пример:

```json
{
  "faces": [
    "data:image/jpeg;base64, /9j/4AAQ...",
    "data:image/jpeg;base64, /9j/4AAQ..."
  ]
}
```

Что умеет `Nested(type: Base64File::class)` сейчас:
- пройти по массиву;
- для каждого элемента сделать `new Base64File($item)`.

Почему этого недостаточно:
- `Base64File` ожидает чистый base64;
- provider шлёт data-uri;
- нужен промежуточный item-level transform.

### Что хотим получить
Нужен explicit способ сказать:

> "это nested-массив, и перед тем как превращать каждый элемент в нужный тип, сначала примени к каждому элементу каст"

Желаемый DX:

```php
#[Nested(
    type: Base64File::class,
    itemCast: DataUriBase64FileCast::class,
)]
public array $faces;
```

---

## Главная идея
Не превращать `Nested` в "универсальную магию", а добавить **одну узкую item-level extension point**:

- `itemCast`

Это позволит:
- не плодить `...ListCast` / `...ArrayCast` для каждого частного кейса;
- сохранить текущую архитектуру:
  - `Nested` отвечает за shape
  - `Cast` отвечает за value transform
- и при этом поддержать nested arrays of scalars/VO cleanly.

---

## Границы рефакторинга

## В scope
- `Attributes/DataTransfer/Nested`
- `Hydrator::hydrateNested(...)`
- тесты на nested collections / arrays
- docs по DTO / Nested / files

## Не в scope
- `DtoSerializer`
- transport wire serialization
- `RequestPartsCollector`
- `DefaultValue`
- `HydrationTypeSelector`

Это именно hydration-level enhancement для nested arrays/collections.

---

## Что именно нужно поддержать

## 1. Array of scalar-like values -> array of transformed values
Пример:

- `list<data-uri-string>` -> `list<Base64File>`

## 2. Array of raw values -> typed collection
Пример:

- `OutputItemCollection`
- при `itemCast` сначала кастуем элемент, потом собираем коллекцию

## 3. `Nested(each: ...)`
Если используется `each`, itemCast должен применяться **после** извлечения inner value.

## 4. Совместимость с текущим `Nested(type: DTO::class)`
Обычные arrays of DTO должны продолжать работать без изменения semantics.

---

## Архитектурные варианты

## Вариант A. Отдельные list-casts для каждого кейса
Например:
- `DataUriBase64FileListCast`

### Плюсы
- быстро
- локально

### Минусы
- не масштабируется
- плодит однотипные касты
- задача по сути structural, а решение частно-прикладное

### Вердикт
Можно как workaround, но не лучший путь для ядра.

## Вариант B. `Nested itemCast`
Добавить в `Nested` явный item-level cast.

### Плюсы
- решает класс кейсов, а не один кейс
- хороший DX
- не ломает текущие DTO
- сохраняет явность

### Минусы
- расширяет core-API `Nested`
- требует аккуратной semantics для приоритетов

### Вердикт
Рекомендуемый вариант.

## Вариант C. Автоматически применять property-level `#[Cast]` к каждому item
### Почему плохо
- `#[Cast]` на свойстве сейчас воспринимается как transform всего значения свойства
- для массива это слишком неоднозначно
- можно сломать существующие кейсы whole-property casts

### Вердикт
Не рекомендую.

---

## Предлагаемый API

## В `Nested`
Добавить поле:

```php
public ?string $itemCast = null
```

Полный пример:

```php
#[Nested(
    type: Base64File::class,
    itemCast: DataUriBase64FileCast::class,
)]
public array $faces;
```

### Контракт
- `itemCast` должен быть `class-string<CastInterface>`
- применяется к каждому item nested-массива
- itemCast получает raw item и может вернуть:
  - scalar
  - object
  - DTO
  - VO

---

## Правила поведения

## 1. `each` сначала, `itemCast` потом
Порядок:
1. если есть `each`, извлекаем inner value
2. если есть `itemCast`, применяем его к каждому item
3. если есть `type`, гидрируем/оборачиваем дальше

Почему:
- `each` — это structural extraction
- `itemCast` — value transform

## 2. `itemCast` применяется только к массивным nested-значениям
То есть:
- к `list<...>`
- не к одиночному объекту/одиночному значению

Иначе semantics станут нечёткими.

## 3. `itemCast` не заменяет `type`
Это два разных шага:
- `itemCast` — преобразовать raw элемент
- `type` — определить, что за сущность в итоге должна получиться

## 4. Если `itemCast` возвращает уже final object нужного типа
Например:
- `DataUriBase64FileCast` возвращает `Base64File`

Тогда hydrator не должен пытаться ещё раз "гидрировать" этот объект как DTO.

## 5. Если `itemCast` не задан
Всё работает как сейчас.

---

## Как это встраивается в код

## 1. `Nested` attribute
Файл:
- `src/Attributes/DataTransfer/Nested.php`

Добавить:
- `public ?string $itemCast = null`

## 2. `Hydrator::hydrateNested(...)`
Главное изменение здесь.

Сейчас логика примерно такая:
- если `each` -> extract
- если `type` -> hydrate each item / wrap collection
- иначе вернуть raw

Нужно вставить item-level stage:

### Для массива
1. `each`
2. `itemCast` для каждого item
3. `type`-stage
4. `wrapCollection`

### Для массива DTO
Если `type` = DTO class:
- после `itemCast` item может быть:
  - raw array -> тогда hydrate DTO
  - уже DTO object -> вернуть как есть
  - уже нужный VO/object -> сохранить как есть, если type-compatible

## 3. Helper methods
Рекомендую вынести:

- `applyNestedEach(array $value, string $path): array`
- `applyNestedItemCast(array $items, Nested $nested, ?PipelineContext $context): array`
- `castNestedItem(mixed $item, string $castClass, ?PipelineContext $context): mixed`

Это уменьшит шум в `hydrateNested`.

---

## Важные edge cases

## 1. `itemCast` + `type = Base64File::class`
Это основной кейс.

Ожидание:
- itemCast вернул `Base64File`
- hydrator не делает `new Base64File(...)` повторно

## 2. `itemCast` + `type = DTO::class`
Возможны два сценария:
- itemCast вернул raw array -> дальше hydrate DTO
- itemCast вернул уже DTO object -> использовать как есть

## 3. `itemCast` + typed collection
После item transform коллекция должна собраться штатно.

## 4. invalid `itemCast`
Если `itemCast` не найден или не реализует `CastInterface`:
- бросать `ConfigurationException`

## 5. non-array nested value
Если `Nested` указывает на массивный кейс, а приходит не массив:
- текущее поведение не ломать
- `itemCast` не пытаться применять "на одиночное значение"

---

## Риски

## Риск 1. Размыть ответственность `Nested`
### Почему
`Nested` из structural attr начинает брать на себя value transform.

### Меры
- ограничить feature только `itemCast`
- не добавлять туда другие "умные" normalization policies
- явно документировать: `itemCast` — item-level transform, не whole-property cast

## Риск 2. Сломать существующие nested DTO кейсы
### Почему
`hydrateNested()` — центральный слой для arrays/collections.

### Меры
- если `itemCast === null`, поведение должно оставаться byte-for-byte прежним
- отдельно прогнать `CollectionIntegrationTest`, polymorphic nested tests, typed collections

## Риск 3. Повторная гидрация уже кастованного объекта
### Почему
Если `itemCast` вернёт `Base64File`, а дальше код всё равно попытается "гидрировать" его как сырой input, будет поломка.

### Меры
- добавить явный `matchesRuntimeType` / instance check перед дальнейшим hydrate

## Риск 4. Слишком магический API
### Почему
Пользователь может не понять разницу между:
- `#[Cast]` на свойстве
- `itemCast` в `Nested`

### Меры
- в docs дать одно короткое правило:
  - `Cast` — transform всего свойства
  - `Nested(itemCast: ...)` — transform каждого элемента массива

---

## Тестовый план

## 1. Main regression
`faces: list<data-uri-string>` + `Nested(type: Base64File::class, itemCast: DataUriBase64FileCast::class)`

Ожидание:
- каждый элемент становится `Base64File`
- пробел после запятой не ломает кейс

## 2. Clean base64 list
Если элементы уже чистый base64:
- тоже должно работать

## 3. Typed collection + itemCast
Проверить, что itemCast не ломает сборку typed collection.

## 4. `Nested(each: ...)` + itemCast
Проверить порядок `each -> itemCast`.

## 5. invalid cast class
Ожидать `ConfigurationException`.

## 6. Existing nested tests
Прогнать и убедиться, что без `itemCast` ничего не изменилось.

---

## Документация

Обновить:
- `docs/guides/dto.md`
- `docs/guides/attributes/data-transfer.md`
- `docs/guides/files.md`
- `docs/glossary/dto.md`
- при необходимости `provider-methodology.md`

### Что объяснить
- `Nested` умеет `itemCast`
- когда использовать `itemCast`
- чем `itemCast` отличается от property-level `#[Cast]`
- example с `list<data-uri-string> -> list<Base64File>`

---

## DX после реализации

### Data-uri images list
```php
#[Nested(
    type: Base64File::class,
    itemCast: DataUriBase64FileCast::class,
)]
public array $faces = [];
```

### DTO list with per-item transform
```php
#[Nested(
    type: AddressDto::class,
    itemCast: NormalizeAddressPayloadCast::class,
)]
public array $addresses = [];
```

---

## Готовность к реализации
План готов к реализации.

Почему:
- проблема локализована
- границы узкие
- решение выбранo
- риски понятны
- тестовый контур очевиден

Это малый/средний scoped refactor, без необходимости трогать transport layer или глобальный DTO contract.

