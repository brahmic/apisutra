# Контракты внешних правил гидратации DTO

- Дата создания: 2026-09-14
- Дата обновления: 2026-09-14

Основание — [план 028](pln-028-readme.md) и [дискуссия dsc-005](../../discussion/dsc-005-declarative-dto-contracts/dsc-005-readme.md).
Решение владельца пакета от 2026-09-14: рекомендации В01–В11 и В13–В17 приняты
как контракт первой версии; по В12 выбрано исключение receiver из запросов клиента.
Альтернативы и причины выбора остаются в дискуссии, основания публичных границ —
в [ADR-002](../../adr/adr-002-external-hydration-rules.md).

Зависимости: изоляция значений metadata — [031](../../completed/pln-031-metadata-value-isolation/pln-031-readme.md);
готовность и ошибки await — [030](../pln-030-continuation-errors/pln-030-readme.md).
Без подключённого набора поведение совпадает с базовой версией после 029, 031 и 030.

## 1. Публичные типы и подключение (В01)

Пространство имён новых типов — `Brahmic\ApiSutra\Serialization\Rules`.

```php
use UnitEnum;

final readonly class HydrationRules
{
    public static function create(?RulePolicy $defaults = null): self;
    /** Возвращает новый набор; повторная регистрация класса — ConfigurationException. */
    public function withDto(string $class, DtoRules $rules): self;
    public function defaults(): RulePolicy;
    public function rulesFor(string $class): ?DtoRules;
}

final readonly class RulePolicy
{
    public function __construct(
        public ?ScalarPolicy $scalars = null,            // null — не задано
        public ?EmptyStringBehavior $emptyString = null,
        public ?NamingStrategy $naming = null,
        public ?DateTimeHydrationPolicy $dateTime = null,
        /** @var array<string, HandlerSpec> cast по имени типа */
        public array $casts = [],
    ) {}
}

enum ScalarPolicy: string
{
    case Legacy = 'legacy';
    case Strict = 'strict';
}

final readonly class DtoRules
{
    public static function create(?RulePolicy $policy = null): self;
    public function field(string $property, FieldRule $rule): self;
    public function extras(string $property): self;
}

final readonly class FieldRule
{
    public static function create(): self;
    public function from(string $path, string ...$fallback): self;
    public function shape(ValueShape $shape): self;
    public function cast(HandlerSpec $cast, ?ValueShape $result = null): self;
    public function noTransform(): self;
    public function required(): self;
    public function forbidExplicitNull(): self;
    public function inputShape(InputShape $shape): self;
    public function default(DefaultSpec $default): self;
    public function policy(RulePolicy $policy): self;
}

enum InputShape: string
{
    case List = 'list';
    case Object = 'object';
}

enum ScalarType: string
{
    case Int = 'int';
    case Float = 'float';
    case Bool = 'bool';
    case String = 'string';
}

final readonly class ValueShape
{
    public static function int(): self;
    public static function float(): self;
    public static function bool(): self;
    public static function true(): self;
    public static function false(): self;
    public static function string(): self;
    public static function mixed(): self;
    public static function scalars(ScalarType ...$types): self;
    public static function nullable(self $shape): self;
    public static function dto(string $class): self;
    public static function list(
        self $item,
        ?string $each = null,
        ?HandlerSpec $itemCast = null,
        bool $normalizeKeys = false,
    ): self;
    public static function variants(
        string $discriminator,
        array $map,
        NestedDiscriminatorMode $mode = NestedDiscriminatorMode::Value,
        NestedUnknownVariant $unknown = NestedUnknownVariant::KeepRaw,
    ): self;
}

final readonly class HandlerSpec
{
    public function __construct(public string $class, public array $args = []) {}
}

final readonly class DefaultSpec
{
    public static function value(int|float|string|bool|array|UnitEnum|null $value, ValueState ...$when): self;
    public static function provider(HandlerSpec $provider, ValueState ...$when): self;
}
```

- `HandlerSpec::args` и `DefaultSpec::value` допускают только scalar, null, enum case
  и массивы из них на любой глубине. `UnitEnum` включает как unit, так и backed enums;
  enum case сохраняется без преобразования в scalar. Любой другой объект, включая
  closure, внутри массива даёт `ConfigurationException` при создании.
  Новый объект для default создаёт provider
  (`DefaultSpec::provider`) на каждое применение. Пустой `when` означает `[Missing]`.
- В `FieldRule` каждая группа задаётся не более одного раза: mapping (`from`),
  преобразование (`shape` | `cast` | `noTransform`), значение по умолчанию, policy.
  Повторный вызов группы и одновременное указание двух вариантов преобразования —
  `ConfigurationException`.
- `ValueShape::variants()` допустим только как элемент `list()`.
- `RulePolicy::casts` на уровне поля запрещены: cast поля задаётся через `cast()`.

Подключение:

- `ClientConfig`: новый последний параметр `?HydrationRules $hydrationRules = null`;
  `with()` переносит набор, явный `hydrationRules: null` отключает его у новой копии.
- `Hydrator::__construct(CastRegistry $casts, ?AttributeMetadataCache $cache = null, ?DtoHydrationProfileResolver $profileResolver = null, ?HydrationRules $rules = null)`.
- `AbstractClient` передаёт набор своему гидратору и сериализатору (раздел 10);
  уже созданный клиент не перенастраивается.
- `Hydrator::forRules(HydrationRules $rules): self` — новый экземпляр с собственным
  включённым metadata cache, без PipelineContext и HTTP. `Hydrator::default()` не меняется.
- Laravel-интеграция собирает набор в provider или factory; конфиг фреймворка
  живых объектов не хранит.

**Компиляция и проверки.** Гидратор компилирует набор при создании: при
`forRules()` и при конструкторе клиента. Проверяются существование классов и свойств,
типы receiver, конфликты с атрибутами, discriminator map, совместимость KeepRaw
с типом контейнера и ссылки между descriptors. DTO-конструкторы не вызываются.
Все ошибки — `ConfigurationException` до преобразования данных.

## 2. Источники правил и их пересечения (В02, В03)

Входные атрибуты поля: **From, Map, Nested, Cast, DateTimeFrom, EmptyStringAsNull,
DefaultValue**. Исходящие **To, DateTimeTo** конфликтов не создают. Map участвует
в обеих сторонах и считается входным. Классовые **DtoHydrate и `#[DtoHydrationProfile]`**
учитываются со всей иерархией PHP. У `AbstractDto` и `AbstractResponseDto`
классовых атрибутов нет.

| Сочетание | Поведение |
| --- | --- |
| `FieldRule` у поля без входных атрибутов | Применяется внешнее правило |
| `FieldRule` и входной атрибут того же свойства | `ConfigurationException` при компиляции |
| Входной атрибут у поля без `FieldRule` | Прежняя атрибутная обработка поля, включая приоритет Nested над Cast |
| `DtoRules` у класса с DtoHydrate/профилем (своим или унаследованным) | `ConfigurationException` при компиляции |
| Атрибут вне входных, в том числе посторонних библиотек | Не учитывается |

Источники policy и casts по типу:

| Класс | Policy (scalars, emptyString, naming, dateTime) | Casts по типу |
| --- | --- | --- |
| Есть `DtoRules` | `FieldRule::policy` → `DtoRules` policy → defaults набора → ядро | Cast поля → casts `DtoRules` → casts набора → встроенные |
| Нет `DtoRules`, нет DtoHydrate/профиля | Defaults набора → ядро; `EmptyStringAsNull`/`DateTimeFrom` поля сильнее | Атрибутный Cast поля → casts набора → встроенные |
| Нет `DtoRules`, есть DtoHydrate/профиль | Только прежнее разрешение 029; defaults набора не применяются | Только профиль и прежние правила |

- `null` в `RulePolicy` означает «не задано» и наследуется. `false`, пустой массив
  и default null — настоящие значения. `noTransform()` явно отключает преобразование
  поля; встроенные проверки выбранного режима остаются.
- Правила ищутся по точному class-string. Родители и интерфейсы не дают внешних
  правил неявно; SDK регистрирует общий `DtoRules` для нескольких классов явно.
- Defaults набора действуют на каждый DTO, в который вошёл этот гидратор, кроме
  классов с профилем. При входе в дочерний DTO policy разрешается заново из его
  класса и набора. Контекстных схем одного класса в зависимости от пути нет.
- Plain-дочерний объект гидратируется только по `ValueShape::dto()`, атрибуту Nested
  или прежней автоматике `DtoInterface`. Defaults не превращают native-класс в DTO.

## 3. Обработчики и область гидратации (В04)

- Обработчик из `HandlerSpec` создаётся на каждое применение: одно значение поля,
  один элемент списка. Экземпляры не делятся между клиентами, вызовами и элементами.
- Жизненный цикл прежних деклараций не меняется: property Cast создаётся на применение,
  `Nested.itemCast` — один на проход списка, профиль разрешается прежним способом.

```php
namespace Brahmic\ApiSutra\Contracts\Interfaces\Casting;

interface ScopedCastInterface extends CastInterface
{
    public function hydrateInScope(mixed $value, HydrationScope $scope): mixed;
}

namespace Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer;

interface ScopedDefaultValueProviderInterface extends DefaultValueProviderInterface
{
    public function resolveInScope(mixed $value, ValueState $state, array $source, HydrationScope $scope): mixed;
}

namespace Brahmic\ApiSutra\Serialization\Rules;

final class HydrationScope
{
    /** @internal создаётся гидратором на корневой вызов */
    public function __construct();
    public function hydrate(array|object $data, string $class): object;
    public function hydrateCollection(array $items, string $class): array;
    public function context(): ?PipelineContext;
}
```

- Гидратор вызывает scoped-метод всегда, когда обработчик реализует интерфейс,
  независимо от регистрации: `HandlerSpec`, property Cast, `Nested(itemCast:)`,
  DefaultValue provider, cast профиля или casts набора. Иначе вызывается прежний метод.
- Scope относится к текущему корневому вызову и тому же гидратору с его набором,
  а без набора — к обычной конфигурации этого гидратора. Он передаётся аргументом
  и не хранится в декларациях, атрибутах или singleton. Вложенные вызовы учитывают
  предел глубины из раздела 13.
- Вызов `Hydrator::default()` внутри обработчика — граница: набор не наследуется.
  Обработчик, передающий работу помощнику, передаёт ему scope.
- Сериализация scoped-методы не использует.

## 4. Strict scalar (В05)

Проверка выполняется до вызова reflection. Legacy сохраняет действующий валидатор.

| Цель в strict | Принять | Отклонить без явного cast |
| --- | --- | --- |
| int | PHP int в поддерживаемом диапазоне | Числовую строку, float, bool |
| float | PHP float; int от −2^53 до 2^53 включительно, точно преобразованный в float | int вне диапазона, числовую строку, bool |
| bool | true, false | 0/1, строки |
| true / false | Только соответствующий literal | Другой bool, прочие типы |
| string | PHP string, включая пустую | Числа, bool, Stringable |
| int\|string и другие scalar union | Точная ветвь с сохранением входного типа; при отсутствии — только расширение int → float | float для int\|string, bool |
| mixed | Любое значение без сужения | — |

- Nullable проверяется отдельно. Выбор ветви не зависит от порядка в union.
  DTO-union требует явной цели или discriminator. DateTime и enum сохраняют
  существующие преобразования.
- Reason: `integer_out_of_range` для значения, которое существующая проверка
  распознаёт как переполнение int; иначе `invalid_field_type`.
- Результат явного cast проверяется по тем же правилам без дополнительной конверсии,
  включая расширение int → float. Для promoted-параметра проверяется тип параметра,
  для прямого заполнения — тип свойства.
- Сравнение границ ±2^53 выполняется без неявного перевода int во float.

## 5. Элементы и вложенные списки (В06)

- Контейнер и элемент описываются раздельно: `list(int())`, `list(nullable(int()))`,
  `list(list(string()))`, `list(dto(ItemDto::class))`, `list(scalars(Int, String))`.
- Bare `array` без `ValueShape::list()` не проверяет форму и элементы.
- `list()` требует последовательные ключи 0..n−1, пустой список допустим.
  Тип и nullable элемента проверяются независимо; strict берётся из policy поля.
- Legacy-policy элемента разрешает старые scalar conversions, но не отключает форму list.
- Ошибка элемента указывает все индексы: `matrix[1][1]`.

## 6. Присутствие, null и форма (В07)

- `required()` требует присутствия ключа во входе; default не заменяет отсутствие.
  Без `required()` действуют прежние правила constructor/default/typed collection.
- `forbidExplicitNull()` отклоняет исходный null до нормализации, DefaultValue и cast.
- Форма входа: `shape(dto())` — object, `shape(list())` — list; для cast и
  `noTransform()` — `inputShape()`.
- Object принимает PHP-объект или массив, не являющийся непустым list. List принимает
  только последовательные ключи; `normalizeKeys: true` переиндексирует ассоциативный вход
  и сохраняет исходные ключи для sourceKey и sourcePath.
- Пустой PHP-массив проходит обе формы. Различие JSON `{}`/`[]`, `{"0":..}`/`[..]`
  и тип числового имени ключа после assoc-декодирования не восстанавливаются.
- Missing и null не проверяются как контейнер. Traversable не считается list.

Reason: `required_field_missing`, `explicit_null_not_allowed`, `invalid_list_shape`,
`invalid_object_shape`.

| Декларация | Вход | Результат |
| --- | --- | --- |
| `?int $count = null`, `forbidExplicitNull()` | Missing | null из default конструктора |
| То же | null | `explicit_null_not_allowed` |
| То же, NullIfEmpty | `""` | Правило не срабатывает; nullable-проверка допускает полученный null |
| `required()` typed collection с default | Missing | `required_field_missing`, без автоподстановки |
| `list()` | Ассоциативный или разреженный массив | `invalid_list_shape` до переиндексации |

## 7. Порядок обработки поля (В08)

Поиск primary/fallback с сохранением происхождения → `required`/`forbidExplicitNull`/форма
входа → нормализация пустой строки → DefaultValue → fallback для Missing → выбранное
преобразование → итоговая проверка типа → аргумент конструктора.

- `cast()` возвращает готовое значение. Необязательный `result` проверяет instance,
  тип и элементы результата без повторной гидратации DTO.
- `shape(list())` выполняет: each → itemCast → проверка или гидратация элемента → обёртка
  коллекции. Готовый DTO от itemCast принимается по совместимому типу; массив или объект
  гидратируется один раз.
- Если default дал новый контейнер, его форма тоже проверяется.
- Field cast сохраняет пропуск нормализации пустой строки, как в 029; itemCast её не отключает.
- Атрибутный Nested + Cast без внешнего правила сохраняет прежнее игнорирование Cast.

## 8. Discriminator (В09)

- Режимы Value/Key и политики KeepRaw/Skip/Error переиспользуются; default — KeepRaw.
- Известный вариант выбирается до гидратации, ошибка его полей остаётся ошибкой.
  Strictness не меняет алгоритм поиска discriminator.
- KeepRaw допустим только если тип контейнера принимает массив. Для typed collection
  конкретных DTO нужен Skip, Error или преобразование SDK; иначе `ConfigurationException`.

## 9. Дополнительные поля (В10, В11)

Остаток вычисляется из неизменяемого локального источника DTO (после BeforeHydrate/computed)
минус объединение потреблённых путей, по дереву сегментов.

| Случай | Остаток |
| --- | --- |
| Есть primary и fallback | Удалён primary; невыбранный fallback сохранён |
| Primary равен null | Удалён primary; fallback не выбирается |
| Все aliases отсутствуют, использован default/provider | Ничего не удаляется |
| Потреблён `profile.id`, рядом `profile.future` | `profile.future` сохранён |
| Потреблён единственный лист `profile.id` | Опустевшая ветка `profile` удалена |
| Исходный непотреблённый `future: []` | Сохранён |
| Два поля читают один путь | Путь удаляется один раз |
| Одно поле читает `profile`, другое `profile.id` | Parent поглощает leaf независимо от порядка |
| Subtree целиком передан дочернему DTO | Потреблён у родителя; внутренние остатки — у ребёнка |
| each передаёт ребёнку часть элемента | Соседи проекции — у владельца списка |

- False, 0, null, пустые строки и исходные пустые контейнеры сохраняются.
  Чтения provider/cast из `source` не считаются потреблением.
- Остаток проекции списка — плотный список записей
  `list<array{sourceKey: int|string, remainder: R}>` в порядке обхода; элементы без остатка
  не добавляются; при отсутствии всех остатков ветка удаляется. `sourceKey` — PHP-ключ
  после декодирования и до нормализации; он не отличает ключ словаря от индекса.
  R — карта полей или такой же список записей для вложенной проекции.
  Нетронутые значения не переоформляются.
- Discriminator: Value-тег без сопоставления остаётся в receiver варианта, с сопоставлением —
  потреблён. Key: payload передаётся варианту, соседи обёртки — у владельца списка,
  имя варианта не превращается в поле. KeepRaw хранит весь элемент, соседи предшествующего
  each — у владельца. Skip удаляет элемент целиком.
- Без receiver у соответствующего уровня сохранение не выполняется.

Receiver:

- Один на DTO, объявлен `DtoRules::extras()`. Public-свойство типа `array` или `?array`,
  всегда получает массив. Поддерживаются параметр конструктора с тем же именем,
  включая promotion с default, и public-свойство класса без конструктора.
- Отклоняются: static, virtual, non-public receiver, неподходящий тип, receiver,
  который конструктор заполняет без параметра, и сочетание receiver с `FieldRule`
  или входными атрибутами. Constructor default не конфликтует.
- Receiver не читается из источника по имени. Исходный ключ с тем же именем сохраняется
  внутри остатка: `{"id":7,"extra":{"future":1}}` → `extra = ["extra" => ["future" => 1]]`.
- Receiver передаётся в единственный вызов конструктора; дописывания после создания нет.

## 10. Исходящее представление receiver (В12)

Решение владельца пакета от 2026-09-14: receiver — механизм чтения и не участвует
в запросах клиента. Рассмотренные варианты — в
[В12 дискуссии](../../discussion/dsc-005-declarative-dto-contracts/dsc-005-readme.md#в12-как-receiver-влияет-на-исходящую-сериализацию).

| Представление | Поведение receiver |
| --- | --- |
| Запрос клиента, чей набор объявляет receiver для класса DTO | Свойство не сериализуется: ни под своим именем, ни развёрнутым в корень |
| `toArray()`, `DtoSerializer::default()`, DtoSerializer без набора | Обычное свойство под своим именем по действующим правилам DX |
| Клиент без набора или набор без `extras()` для класса | Прежняя сериализация свойства |

Пример: DTO с `id = 7` и `extra = ["future" => false]` в теле запроса клиента с набором
даёт `{"id":7}`; тот же объект через `toArray()` — `["id" => 7, "extra" => ["future" => false]]`.

### Представление, которое строит ядро

Правило действует в частях запроса, где сериализатор преобразует объекты: body, BodyRoot,
multipart-body и query. Header, path и file DTO не сериализуют; их поведение не меняется.

| Значение части запроса | Представление |
| --- | --- |
| `DtoInterface`-объект | Прежний DtoSerializer без receiver своего класса |
| Plain-объект класса с `extras()` в наборе | Массив публичных свойств без receiver; значения свойств обрабатываются прежним путём; при отсутствии оставшихся свойств — пустой JSON-объект |
| Массив или plain-объект, внутри которого через элементы массивов и публичные свойства есть объект класса с receiver | Заменяются только контейнеры на пути к такому объекту; прочие значения не меняются |
| Значение без объектов классов с receiver | Прежнее представление без изменений |

- Обход не раскрывает объекты, которые сами строят представление (`JsonSerializable`,
  `toArray()` вне `DtoInterface`, `Stringable`), а также `DateTimeInterface`, enum и closure.
- Обход не создаёт DTO, не вызывает конструкторы и не изменяет исходные объекты.
  Если набор не объявляет ни одного receiver, обход не выполняется.
- JSON plain-объекта и массива его публичных свойств совпадают, поэтому остальные поля
  сохраняют прежнее представление.

### Непрозрачные преобразования

Ядро не может исключить receiver из результата обработчика, который получает объект целиком.
В первой версии такие сочетания отклоняются, гарантия исключения сохраняется:

| Сочетание | Поведение |
| --- | --- |
| Класс с `extras()` реализует `JsonSerializable` или `Stringable` либо имеет `toArray()` без `DtoInterface` | `ConfigurationException` при компиляции набора |
| Property Cast или cast по типу из реестра клиента/профиля получает значение, в котором по правилам обхода есть объект класса с receiver (например, `#[BodyRoot] #[Cast(JsonCast::class)]` на DTO с receiver) | `SerializationException` до вызова cast и до HTTP; в результате — `serialization_error`; message называет класс и свойство без значений |
| Объект, сам строящий представление, хранит объект класса с receiver в своём состоянии | Граница гарантии: ядро такие объекты не раскрывает, их представление — ответственность SDK |

Безопасный контракт преобразования значений с receiver можно добавить позже, не меняя
отказ по умолчанию.

### Общие правила

- Исключение определяется классом DTO и набором клиента, а не происхождением объекта:
  вручную созданный DTO исключает receiver так же, как гидратированный.
- `Serializer` получает тот же `HydrationRules` новым последним необязательным аргументом
  конструктора; `AbstractClient` передаёт набор. Список receiver берётся из скомпилированного
  набора, а не из metadata cache.
- Атрибуты исходящего представления и частей запроса на receiver — `To`, `DateTimeTo`,
  `Query`, `Body`, `BodyRoot`, `Header`, `Path`, `File` — дают `ConfigurationException`
  при компиляции набора.
- Prepared request, `requestDebug()`, идентичность HTTP cache и логи запроса строятся
  из представления уже без receiver.
- Отправка неописанных полей обратно — задача явной модели запроса SDK. Явное включение
  receiver в запросы в первой версии не предусмотрено; его можно добавить позже отдельной
  opt-in настройкой без изменения этого поведения по умолчанию.

## 11. Происхождение и безопасный лог (В13–В15)

```php
// HydrationException: новые необязательные аргументы в конце конструктора
public readonly ?string $sourcePath = null;          // JSON Pointer
public readonly ?SourcePathKind $sourcePathKind = null;
/** @var list<string> */
public readonly array $sourceCandidates = [];        // JSON Pointer кандидатов при Missing

public function logContext(): array;

enum SourcePathKind: string
{
    case Resolved = 'resolved';
    case Expected = 'expected';
    case Boundary = 'boundary';
    case Unavailable = 'unavailable';
}
```

- `path` сохраняет прежний формат DTO-пути и порядковые индексы. `sourcePath` —
  JSON Pointer (`~` → `~0`, `/` → `~1`, корень — пустая строка). Пример: DTO-путь
  `data.items[1].id`, sourcePath `/data/rows/1/value/record_id`.
- Resolved — точное расположение значения; Expected — primary при Missing
  (кандидаты в `sourceCandidates`); Boundary — ближайший известный вход пользовательского
  преобразования; Unavailable — `sourcePath = null`.
- Точное происхождение ведётся только для операций ядра: aliases, each, discriminator,
  unwrap, пагинация, нормализация ключей. Computed, BeforeHydrate, объектный `toArray()`,
  cast и provider создают границу. Исходный ключ элемента сохраняется до нормализации и Skip.
- Префиксы добавляются один раз; сведения о сегментах переносятся при `prependPath`
  и оборачивании.
- Новые поля заполняются только гидратором с подключённым набором. Прежние ошибки
  сохраняют прежний контекст.
- `context()` содержит полную source-диагностику для результата и вызывающего кода.
  `logContext()` оставляет объявленные сегменты mapping/discriminator и позиционные
  индексы настоящих списков, остальные сегменты заменяет на `*`. Числовой ключ
  с неизвестным происхождением маскируется. Message содержит только безопасный DTO-путь.
- `ExecutionResultBuilder` и прочие автоматические логгеры используют `logContext()`;
  результат — `context()`. Правило не ослабляется при `debug=false`.

## 12. Входы исполнения (В16)

- Один гидратор клиента с набором обслуживает Returns, обе ветки пагинации,
  итог CompositeFlow и continuation. Await получает этот гидратор по контракту 030.
- `Hydrator::forRules()` даёт standalone-исполнитель с тем же набором и тем же результатом.
- HTTP cache хранит исходный ответ; при cache hit ответ гидратируется набором текущего
  клиента. Ключ HTTP cache не меняется.
- Ненулевой результат response handler, RawResponse и Download сохраняют отдельные ветки.
- Конструктор DTO вызывается один раз на узел гидратации.

## 13. Кеш правил и рекурсия (В17)

- Общий reflection cache хранит только факты класса; изоляция значений — по контракту 031.
- Скомпилированные descriptors хранятся в экземпляре гидратора с одним набором;
  ключ — class-string внутри этого экземпляра. Глобального кеша правил нет.
  Обработчики, payload, потреблённые пути и стек происхождения в кеш не входят.
- Рекурсивные ссылки разрешаются через состояния «разрешается/готово»; `Node → Node` допустим.
- Предел вложенности нового пути — 512 активных DTO/list-узлов, корень — первый уровень,
  соседние ветки не суммируются: `HydrationException(hydration_depth_exceeded)` с текущим путём.
- Повтор одного PHP-объекта среди активных предков — `cyclic_hydration_input`.
  Общий объект в независимых ветках циклом не является.

## 14. Сквозной пример

```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Serialization\Rules\DtoRules;
use Brahmic\ApiSutra\Serialization\Rules\FieldRule;
use Brahmic\ApiSutra\Serialization\Rules\HydrationRules;
use Brahmic\ApiSutra\Serialization\Rules\RulePolicy;
use Brahmic\ApiSutra\Serialization\Rules\ScalarPolicy;
use Brahmic\ApiSutra\Serialization\Rules\ValueShape;

// Проект API: SDK описывает правила один раз при сборке клиента.
$rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
    ->withDto(ReportDto::class, DtoRules::create()
        ->field('id', FieldRule::create()->from('record_id', 'legacy_id'))
        ->field('owner', FieldRule::create()->from('profile')->shape(ValueShape::dto(OwnerDto::class)))
        ->field('items', FieldRule::create()->from('rows')->required()
            ->shape(ValueShape::list(ValueShape::dto(RecordDto::class), each: 'value')))
        ->field('ids', FieldRule::create()->shape(ValueShape::list(ValueShape::int())))
        ->field('count', FieldRule::create()->forbidExplicitNull())
        ->extras('extra'))
    ->withDto(OwnerDto::class, DtoRules::create()
        ->field('id', FieldRule::create()->from('user_id'))
        ->extras('extra'))
    ->withDto(RecordDto::class, DtoRules::create()
        ->field('id', FieldRule::create()->from('record_id')));

$client = new ReportsClient(new ClientConfig(baseUrl: 'https://provider.example', hydrationRules: $rules), $transport);
$report = Hydrator::forRules($rules)->hydrate($payload, ReportDto::class);
```

`ReportDto`, `OwnerDto` и `RecordDto` — обычные `final readonly` классы без атрибутов
и интерфейсов ApiSutra. Ожидаемые результаты для этого графа — в
[UC-01](../../discussion/dsc-005-declarative-dto-contracts/product-use-cases.md#uc-01-получить-готовый-граф-моделей-через-обычный-вызов-sdk)
и [acceptance.md](acceptance.md).

## 15. Порции реализации

| Порция | Разделы | Готовность к коду |
| --- | --- | --- |
| A: правила, strict и форма | 1–8, 12–13; внутренний учёт происхождения | Готова; интеграция metadata ждёт 031, await — 030 |
| B: дополнительные поля | 9–10 | Готова; интеграция metadata ждёт 031 |
| C: sourcePath и безопасный лог | 11 | Готова после необходимых частей A/B |
