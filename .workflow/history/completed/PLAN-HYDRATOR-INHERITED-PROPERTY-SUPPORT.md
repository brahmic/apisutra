# План: поддержка inherited property hydration вне constructor chain

## Проблема и ожидания
Сейчас `apisutra` гидрирует DTO по модели `constructor-first`:

- payload читается полностью;
- значения нормализуются/кастуются;
- объект создаётся через конструктор;
- в конструктор передаются только параметры, которые входят в effective constructor chain.

Из-за этого возникает ограничение:

- если DTO наследует часть свойств от базового класса,
- а в самом конечном DTO объявлены дополнительные гидрируемые свойства,
- но они **не входят** в effective constructor chain,
- hydrator не инициализирует их в объекте.

Для SDK-разработчика это неудобно, потому что мешает естественно строить иерархию DTO вида:

```php
final readonly class OtherLastNamesBlockDto extends BaseBlockDto
{
    #[From('result')]
    public ?OtherLastNamesResultDto $result;
}
```

### Что хотим получить
Нужна поддержка такой модели, при которой:

1. DTO может наследовать часть полей от базы.
2. Конечный DTO может объявлять новые гидрируемые публичные свойства без собственного конструктора.
3. `readonly` DTO продолжают корректно работать.
4. Текущий constructor-first подход не ломается для уже работающих DTO.
5. Поведение остаётся предсказуемым и не превращается в "магическое доприсваивание чего угодно куда угодно".

---

## Главная цель
Расширить контракт hydrator-а с:

- **constructor-first only**

до:

- **constructor-first + controlled property-fill fallback**

То есть:

- всё, что покрывается effective constructor chain, инициализируется как и раньше через конструктор;
- всё, что найдено в payload, но не покрыто constructor chain, может быть доинициализировано напрямую в свойства объекта по строго определённым правилам.

---

## Ключевой принцип будущей модели
Hydrator остаётся **constructor-first**, а не становится "чисто property-based".

Это важно:

- конструкторы как источник инвариантов DTO сохраняются;
- текущий DX и shape большинства SDK не ломаются;
- новая логика добавляется как fallback для непокрытых constructor chain полей.

То есть целевая модель:

1. constructor path — основной
2. property-fill path — только для остаточных гидрируемых свойств

---

## Что именно считается supported contract после рефакторинга

## 1. Поддерживаемый сценарий
Будет считаться нормальным supported pattern, если:

- DTO class наследует от базового DTO;
- базовый DTO содержит часть constructor-backed полей;
- дочерний DTO содержит дополнительные публичные гидрируемые свойства;
- у дочернего DTO нет собственного конструктора;
- hydrator сам доинициализирует эти свойства после constructor phase.

## 2. Поддерживаемый сценарий для readonly
То же самое должно поддерживаться и для `readonly` DTO, если:

- свойства ещё не были инициализированы;
- hydrator делает controlled post-constructor assignment ровно один раз.

## 3. Что не обещаем
Мы не обещаем:

- произвольную mutation model после полной инициализации объекта;
- lazy post-hydration assignment в непредсказуемых местах;
- множественное переопределение уже инициализированных readonly свойств;
- implicit merge нескольких несовместимых property definitions в иерархии.

---

## Границы рефакторинга

## В scope
- `Hydrator`
- metadata extraction для hydration
- constructor args building
- object instantiation strategy
- controlled assignment remaining properties
- тесты на inheritance / readonly / defaults / nullable / nested
- документация `apisutra` по DTO/hydration

## Вне scope
- `DtoSerializer`
- wire/DX serialization split
- `RequestPartsCollector`
- enum/date-time transport semantics
- `ClientConfig`
- request attributes / query/path/header serialization

То есть этот рефакторинг касается **только hydration contract**.

---

## Что важно не сломать

## 1. Existing constructor-first DTO
Текущие DTO, которые полностью покрываются constructor chain, должны работать как раньше без изменения поведения.

## 2. `readonly` DTO
Нельзя ввести схему, где readonly properties:

- получают двойную инициализацию;
- могут быть случайно перезаписаны;
- становятся зависимы от неявных поздних модификаций.

## 3. `DefaultValue`
Логика `DefaultValue`, `Missing`, `Null`, provider-based defaults, auto-default collections должна остаться семантически прежней.

## 4. Nested hydration / casts / enums / dates
Все преобразования значения должны по-прежнему происходить **до instantiation phase**, а не переноситься в assignment layer.

## 5. Constructor invariants
Если DTO deliberately требует constructor-initialized shape, это не должно ломаться и не должно обходиться "магическим setValue где получится".

---

## Почему текущая модель не покрывает кейс

Сейчас hydrator:

1. собирает `$values` по всем свойствам;
2. извлекает список constructor parameters;
3. передаёт в `newInstanceArgs(...)` только то, что совпало по именам с конструктором;
4. всё, что осталось, теряется.

То есть проблема **не** в том, что свойство нельзя объявить без конструктора,
а в том, что hydrator не делает post-constructor fill.

---

## Архитектурные варианты

## Вариант A. Оставить всё как есть
Сказать, что такие DTO должны быть только constructor-backed, а для leaf DTO нужны промежуточные базы.

### Плюсы
- ничего не менять в ядре
- нулевой риск регрессий

### Минусы
- плохой DX для SDK-разработчиков
- много лишнего boilerplate
- inheritance DTO shape остаётся искусственно жёстким

### Вердикт
Отклоняем.

## Вариант B. Полностью перейти на property-based hydration
Создавать DTO без конструктора и всегда заполнять свойства через reflection.

### Плюсы
- максимально гибко

### Минусы
- ломает constructor-first модель
- ослабляет инварианты DTO
- высокие риски побочных эффектов
- плохо согласуется с существующей философией пакета

### Вердикт
Отклоняем.

## Вариант C. Hybrid model: constructor-first + property-fill fallback
Сначала инициализировать всё, что покрывается constructor chain, потом дозаполнять остаточные свойства.

### Плюсы
- минимально инвазивно
- сохраняет текущую модель
- решает реальный SDK кейс
- не требует массового переписывания DTO

### Минусы
- усложняет hydrator
- требует аккуратных правил по readonly/defaults/missing

### Вердикт
Это рекомендуемый вариант.

---

## Рекомендуемая целевая модель

## Шаг 1. Normalize / resolve values
Как и сейчас:

- читаем payload;
- применяем `From` / `Map` / naming fallback;
- применяем `DefaultValue`;
- гидрируем nested;
- применяем casts / enums / dates;
- формируем итоговый `$values` по всем properties.

На этом этапе семантика значений не меняется.

## Шаг 2. Split values into two buckets
После этого hydrator должен разделить значения на:

1. `constructorValues`
2. `remainingPropertyValues`

### Правило
- если имя свойства совпадает с constructor parameter -> идёт в constructor bucket
- иначе -> в remaining property bucket

## Шаг 3. Instantiate object

### Case A. Нет remaining properties
Оставляем старый fast-path:

- `newInstanceArgs($args)`

### Case B. Есть remaining properties
Новый path:

1. создать объект через `newInstanceWithoutConstructor()`
2. вызвать конструктор рефлексией на этом экземпляре
3. затем выполнить controlled assignment remaining properties

## Шаг 4. Assign remaining properties
Для каждого свойства из `remainingPropertyValues`:

- получить `ReflectionProperty`
- убедиться, что свойство ещё не инициализировано
- выполнить `setValue(...)`

Если свойство уже инициализировано:
- не перезаписывать
- либо считать это ошибкой контракта, если возникло неожиданно

Рекомендация:
- на первой фазе **не перезаписывать и не падать**, если значение уже инициализировано через constructor,
  потому что такое свойство вообще не должно было попасть в remaining bucket.
- Но если попало — это symptom bug-а в split logic, и лучше бросить `ConfigurationException`, чтобы не скрывать дефект.

---

## Правила поведения

## 1. Constructor всегда приоритетнее property-fill
Если свойство покрыто constructor chain, оно считается constructor-owned.

Hydrator не должен:
- дублировать assignment
- переопределять это значение post-fill phase

## 2. Property-fill применяется только к public data properties
Пока `apisutra` и так ориентирован на public DTO data properties.

Новый fallback тоже должен работать только с такими свойствами.

Это снижает магию и согласуется с текущим serializer/hydrator contract.

## 3. Nullable missing properties вне constructor
Надо решить поведение отдельно.

### Вариант A
Не трогать — свойство остаётся неинициализированным.

### Вариант B
Если свойство nullable и отсутствует, явно ставить `null`.

### Рекомендация
Выбрать **B**.

Почему:
- иначе nullable property без конструктора будет ловушкой;
- для SDK-разработчика ожидание "`?Type` -> `null`, если данных нет" естественно;
- это особенно важно для `readonly`.

То есть:
- если non-constructor public property nullable
- и значение отсутствует после `DefaultValue`
- hydrator должен уметь инициализировать его `null`

## 4. Non-nullable missing properties вне constructor
Тут поведение должно оставаться строгим.

Рекомендация:
- если non-constructor property non-nullable
- и после всех defaults оно не получило значение
- hydrator не должен молча оставлять его пустым

Варианты:
1. оставить текущее поведение и позволить упасть позже
2. бросать предсказуемую `ConfigurationException`

Рекомендация:
- лучше бросать предсказуемую `ConfigurationException`

Это уменьшит неочевидные runtime ошибки.

## 5. `DefaultValue`
`DefaultValue` должен продолжать работать до split phase.

То есть:
- property-fill path уже получает финальное resolved value
- ему не нужно заново решать, применять default или нет

## 6. Typed collections auto-default
Логика built-in `Missing -> []` для typed collections должна сохраниться до instantiation и работать одинаково для:
- constructor-backed properties
- remaining properties

## 7. `computed()`
`computed()` по-прежнему выполняется до hydration и не требует специальной логики для property-fill path.

## 8. Nested DTO
Nested DTO должны быть уже гидрированы до instantiation parent DTO.

То есть property-fill path работает не с raw arrays, а уже с готовыми объектами/значениями.

---

## Что менять в коде

## A. `Hydrator::hydrate()`

### Сейчас
- `$values`
- `constructor`
- `newInstanceArgs(...)`

### Нужно
Разбить на отдельные шаги:

1. `resolveHydratedValues(...)`
2. `buildConstructorArgs(...)`
3. `buildRemainingPropertyAssignments(...)`
4. `instantiateDto(...)`
5. `assignRemainingProperties(...)`

Это нужно и для читаемости, и для контроля edge cases.

## B. New helper methods

Рекомендуемые приватные методы в `Hydrator`:

- `buildConstructorArguments(array $values, array $constructor): array`
- `buildRemainingPropertyAssignments(array $values, array $constructor): array`
- `instantiateViaConstructorOnly(string $dtoClass, array $args): object`
- `instantiateWithPropertyFill(string $dtoClass, array $args, array $assignments): object`
- `invokeConstructorOnObject(ReflectionClass $reflection, object $object, array $args): void`
- `assignPropertyValues(object $dto, array $assignments): void`

## C. Metadata / constructor map
Для эффективности можно построить set имён constructor parameters один раз:

- `array_flip(array_column($constructor, 'name'))`

И использовать его для split logic.

## D. Validation / guard logic
Нужно добавить guards:

- нельзя setValue в уже инициализированное readonly property
- нельзя silently skip non-nullable missing non-constructor property
- нельзя делать property-fill для static properties

---

## Edge cases, которые надо продумать заранее

## 1. Nullable property с `= null`
Для `readonly class` это может быть невалидный синтаксис.

Нужно не ориентироваться на default value как на основной supported path для readonly properties.
Supported path должен быть:
- либо constructor init
- либо hydrator property-fill

## 2. Constructor default + same property exists as inherited/public
Если constructor покрывает свойство и у него есть default, то property-fill не должен пытаться его перезатереть.

## 3. Promoted properties in parent constructor
Это обычный и поддерживаемый кейс.

Новый property-fill не должен ломать promoted parent properties.

## 4. `with(...)`
`AbstractDto::with()` остаётся constructor-driven и сейчас смотрит на constructor params.

Это важно:
- рефакторинг hydrator-а не должен автоматически означать рефакторинг `with()`

Но надо осознавать, что после добавления non-constructor hydrated properties:
- `with()` может не знать про них как про constructor-owned shape

### Рекомендация
Пока не менять `with()` в этом рефакторинге.
Это отдельный follow-up question.

## 5. Serialization symmetry
`DtoSerializer` и так читает все public properties по reflection.

То есть после этого рефакторинга:
- hydration станет лучше поддерживать inherited property shape
- serializer уже поддерживает его нормально

Это хороший аргумент в пользу изменения.

---

## Риски

## Риск 1. Сломать текущие constructor-backed DTO
### Почему
Hydrator — центральный слой, через него проходит почти всё.

### Меры
- сохранить fast-path, если remaining properties нет
- не менять semantics existing constructor-only case
- прогнать весь пакетный suite

## Риск 2. Неочевидные ошибки с readonly
### Почему
Reflection assignment в readonly object чувствителен к порядку инициализации.

### Меры
- инициализировать свойства только один раз
- добавить guards на уже инициализированные props
- покрыть отдельными unit tests:
  - parent promoted + child property
  - child nullable property
  - child non-nullable property

## Риск 3. Размытие constructor invariants
### Почему
Можно случайно создать ощущение, что конструкторы больше не важны.

### Меры
- явно сохранить constructor-first contract в docs
- property-fill позиционировать как fallback только для residual hydrated props

## Риск 4. Латентные ошибки missing/non-nullable
### Почему
Раньше такие shape case могли просто не поддерживаться, теперь они начнут partially работать.

### Меры
- для non-nullable missing вне constructor делать явную ошибку
- для nullable missing — явный `null`

## Риск 5. Слишком магический behavior
### Почему
Если не описать правила жёстко, пользователи не будут понимать, почему часть полей нужно в constructor, а часть — нет.

### Меры
- обновить docs
- отдельно описать supported DTO inheritance shape
- добавить migration guidance для SDK authors

---

## Тестовый план

## 1. Constructor-only regression safety
- DTO полностью покрыт constructor chain
- поведение не меняется

## 2. Parent constructor + child property
Ключевой новый кейс:

```php
abstract readonly class BaseBlockDto extends AbstractDto
{
    public function __construct(
        public ReportFoundState $found,
        public ReportBlockStatus $status,
    ) {}
}

final readonly class OtherLastNamesBlockDto extends BaseBlockDto
{
    #[From('result')]
    public ?OtherLastNamesResultDto $result;
}
```

Ожидание:
- `found` и `status` инициализированы через constructor
- `result` инициализирован hydrator-ом post-constructor

## 3. Nullable missing non-constructor property
Ожидание:
- получает `null`

## 4. Non-nullable missing non-constructor property
Ожидание:
- предсказуемая ошибка

## 5. Nested DTO в non-constructor property
Ожидание:
- nested hydration работает

## 6. Enum / date / cast в non-constructor property
Ожидание:
- все transforms применяются до assignment

## 7. `DefaultValue`
Ожидание:
- и для constructor-backed
- и для property-fill backed shape работает одинаково

## 8. Inheritance chain > 2
Пример:
- grandparent
- parent
- child

Ожидание:
- все значения инициализируются корректно

---

## Документация

Нужно обновить:

- `docs/guides/dto.md`
- `docs/guides/provider-methodology.md`
- `docs/guides/provider-checklist.md`
- `docs/glossary/dto.md`
- SDK migration/checklist docs, если захотим рекомендовать новый pattern

### Что важно явно зафиксировать
- constructor-first contract сохраняется
- hydrator теперь поддерживает inherited public properties вне constructor chain
- это fallback path, а не полная отмена constructor model

---

## DX после рефакторинга

## Пример 1. База + leaf property без собственного конструктора
```php
abstract readonly class BaseBlockDto extends BaseResponseDto
{
    public function __construct(
        public ReportFoundState $found,
        public ReportBlockStatus $status,
    ) {}
}

final readonly class OtherLastNamesBlockDto extends BaseBlockDto
{
    #[From('result')]
    public ?OtherLastNamesResultDto $result;
}
```

Это должно гидрироваться штатно.

## Пример 2. Несколько уровней
```php
abstract readonly class BaseReportBlockDto extends BaseResponseDto
{
    public function __construct(
        public ReportFoundState $found,
    ) {}
}

abstract readonly class BaseStatusBlockDto extends BaseReportBlockDto
{
    public function __construct(
        ReportFoundState $found,
        public ReportBlockStatus $status,
    ) {
        parent::__construct($found);
    }
}

final readonly class OtherLastNamesBlockDto extends BaseStatusBlockDto
{
    #[From('result')]
    public ?OtherLastNamesResultDto $result;
}
```

Тоже должно работать.

## Пример 3. Nullable property без payload
```php
final readonly class OptionalPayloadBlockDto extends BaseBlockDto
{
    #[From('result')]
    public ?array $result;
}
```

Если `result` отсутствует, ожидаем:
- `$dto->result === null`

---

## Рекомендуемый порядок реализации

1. Зафиксировать behavioral rules
2. Перестроить `Hydrator` на split buckets
3. Реализовать `newInstanceWithoutConstructor()` path только для residual-property case
4. Добавить guards на readonly / already initialized / missing non-nullable
5. Добавить тесты inheritance-first
6. Прогнать весь suite
7. Обновить docs

---

## Definition of Done

Рефакторинг считается завершённым, если:

1. Конструкторные DTO продолжают работать без изменения поведения.
2. DTO с inherited public properties вне constructor chain гидрируются штатно.
3. Nullable non-constructor props при missing получают `null`.
4. Non-nullable missing props вне constructor chain дают предсказуемую ошибку.
5. `readonly` DTO работают корректно.
6. Nested/cast/enum/date behavior для таких props покрыт тестами.
7. Документация явно описывает новый supported contract.

