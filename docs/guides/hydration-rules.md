# Внешние правила гидратации DTO

Чтобы использовать модели без атрибутов ApiSutra, соберите неизменяемый `HydrationRules`
и передайте его в `ClientConfig::hydrationRules`. Один набор описывает mapping, вложенные
DTO, строгие типы, присутствие полей и сохранение дополнительных данных. Для обработки
без клиента используйте `Hydrator::forRules($rules)`: Laravel, контейнер и HTTP не нужны.

## Сквозной пример

Классы DTO в SDK размещаются в отдельных файлах. Здесь они приведены вместе,
чтобы пример можно было запустить после подключения Composer autoload.

```php
namespace Example\HydrationRules;

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Serialization\Rules\DtoRules;
use Brahmic\ApiSutra\Serialization\Rules\FieldRule;
use Brahmic\ApiSutra\Serialization\Rules\HydrationRules;
use Brahmic\ApiSutra\Serialization\Rules\RulePolicy;
use Brahmic\ApiSutra\Serialization\Rules\ScalarPolicy;
use Brahmic\ApiSutra\Serialization\Rules\ValueShape;

final readonly class EntryDto
{
    public function __construct(public int $id, public array $_extra = []) {}
}

final readonly class ReportDto
{
    /** @param list<EntryDto> $items @param list<int> $ids */
    public function __construct(
        public EntryDto $owner,
        public array $items,
        public array $ids,
        public ?int $count = null,
        public array $_extra = [],
    ) {}
}

$rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
    ->withDto(EntryDto::class, DtoRules::create()
        ->field('id', FieldRule::create()->from('record_id', 'id'))
        ->extras('_extra'))
    ->withDto(ReportDto::class, DtoRules::create()
        ->field('owner', FieldRule::create()->shape(ValueShape::dto(EntryDto::class)))
        ->field('items', FieldRule::create()->from('rows')->shape(
            ValueShape::list(ValueShape::dto(EntryDto::class), each: 'value'),
        ))
        ->field('ids', FieldRule::create()->shape(ValueShape::list(ValueShape::int())))
        ->field('count', FieldRule::create()->forbidExplicitNull())
        ->extras('_extra'));

$config = new ClientConfig(baseUrl: 'https://api.example', hydrationRules: $rules);
$source = [
    'owner' => ['record_id' => 7, 'future' => false],
    'rows' => [['value' => ['record_id' => 8], 'meta' => ['revision' => 2]]],
    'ids' => [1, 2],
    'next_feature' => null,
];
$dto = Hydrator::forRules($rules)->hydrate($source, ReportDto::class);
// owner.id = 7, owner._extra = ['future' => false], items[0].id = 8, count = null.
// _extra содержит next_feature и остаток rows с meta (форма описана ниже).
```

Передайте `$config` своему SDK-клиенту. `#[Returns(ReportDto::class)]` использует
тот же набор. Необязательный последний параметр `rules` есть также у конструкторов
`Hydrator`, `DtoSerializer` и `Serializer`. Клиент передаёт набор обоим направлениям.
`$config->with(hydrationRules: null)` создаёт конфигурацию без набора;
`with()` без override сохраняет исходный набор.

`Hydrator::default()` и `DTO::from()` набор клиента не наследуют. Для одинакового
поведения standalone и клиента передавайте один набор явно. В Laravel собирайте
набор в provider/factory, а не сохраняйте живые descriptors в кешируемом config.

## Правила и проверка конфигурации

Все публичные descriptors находятся в `Brahmic\ApiSutra\Serialization\Rules`.
Методы builders возвращают новый объект. Класс регистрируется точно по имени;
его схема не меняется в зависимости от пути в графе.

| API | Назначение |
| --- | --- |
| `HydrationRules::create(?RulePolicy $defaults = null)` | Общие defaults набора |
| `withDto(string $class, DtoRules $rules)` | Правила одного класса; повтор запрещён |
| `defaults()`, `rulesFor(string $class)` | Чтение defaults и правил класса |
| `DtoRules::create(?RulePolicy $policy = null)` | Policy класса |
| `field(string $property, FieldRule $rule)` | Одно правило физического свойства |
| `extras(string $property)` | Одно свойство для остатка источника |
| `FieldRule::create()->from(string $path, string ...$fallback)` | Primary и запасные пути через точку; null primary считается найденным |
| `shape(ValueShape $shape)` | Преобразование вложенной формы |
| `cast(HandlerSpec $cast, ?ValueShape $result = null)` | Готовое значение от cast; result только проверяет его |
| `noTransform()` | Явное отсутствие преобразования; native-тип всё равно проверяется |
| `required()`, `forbidExplicitNull()` | Присутствие ключа и запрет исходного null |
| `inputShape(InputShape $shape)` | Проверка Object/List перед cast или noTransform |
| `default(DefaultSpec $default)`, `policy(RulePolicy $policy)` | Default и policy поля |

Повторные field/receiver, повтор группы from/default/policy или сочетание нескольких
преобразований (`shape`, `cast`, `noTransform`) дают `ConfigurationException`.
Гидратор компилирует набор при `forRules()` или создании клиента: проверяет классы,
свойства, receiver, ссылки и discriminator map. DTO-конструкторы при этом не вызываются.

`FieldRule` конфликтует с входными атрибутами **того же свойства**: From, Map, Nested,
Cast, DateTimeFrom, EmptyStringAsNull, DefaultValue. Атрибуты соседнего поля остаются
рабочими. To, DateTimeTo и посторонние атрибуты не конфликтуют. `DtoRules` конфликтует
с собственным или унаследованным DtoHydrate/DtoHydrationProfile.

## Policy и строгие типы

`RulePolicy` принимает nullable `scalars`, `emptyString`, `naming`, `dateTime`
и массив `casts` вида `имя PHP-типа => HandlerSpec`. Nullable означает «не задано».
Policy поля перекрывает policy класса, затем defaults набора, затем defaults ядра.
`dateTime` — целый `DateTimeHydrationPolicy`, без слияния отдельных полей объекта.

Приоритет преобразования: cast поля → casts класса → casts набора → встроенные
преобразования. `RulePolicy::casts` на уровне поля запрещён: используйте `cast()`.
`noTransform()` выключает этот выбор. Класс с профилем, не зарегистрированный через
`DtoRules`, полностью сохраняет правила профиля и игнорирует defaults набора.
При входе в дочерний DTO policy выбирается заново: Legacy родителя не отменяет strict ребёнка.

| Цель при `ScalarPolicy::Strict` | Допустимый вход |
| --- | --- |
| int | PHP int |
| float | PHP float; int от −2^53 до 2^53 включительно с точным расширением |
| bool, true, false | Соответствующий bool/literal |
| string | Только строка, включая пустую |
| scalar union | Сначала точная ветвь с сохранением типа; затем расширение int → float |
| mixed | Любое значение |

Строки с числами и bool не становятся числами в strict. Переполнение int сохраняет
reason `integer_out_of_range`, прочие несовпадения — `invalid_field_type`.
Nullable проверяется отдельно; DateTime и enum сохраняют встроенные преобразования.
Результаты casts тоже проверяются до reflection. `ScalarPolicy::Legacy` разрешает
прежние scalar conversions. Набор по умолчанию использует Legacy.

## Формы, присутствие и defaults

`ValueShape` предоставляет `int()`, `float()`, `bool()`, `true()`, `false()`, `string()`,
`mixed()`, `scalars(ScalarType ...$types)`, `nullable(ValueShape $shape)`, `dto(string $class)`,
`list(ValueShape $item, ?string $each = null, ?HandlerSpec $itemCast = null, bool $normalizeKeys = false)`.
Списки могут быть вложенными. PHPDoc `list<int>` и bare `array` сами элементы не проверяют.
Plain native-класс без `dto()`, Nested или DtoInterface не гидратируется автоматически.

`list()` требует плотные ключи 0..n−1; словарь и разреженный массив дают
`invalid_list_shape`. `normalizeKeys: true` разрешает словарь и переиндексирует результат,
сохраняя исходные ключи в диагностике и extras. Traversable не считается списком.
`dto()` допускает объект или массив формы object; непустой list даёт `invalid_object_shape`.
Пустой PHP-массив допустим в обеих формах: после assoc-декодирования различие `{}`/`[]`
и `{"0":...}`/`[...]` восстановить нельзя.

Порядок поля: поиск → required/null/форма → нормализация пустой строки → default →
обработка Missing → преобразование → проверка native-типа → единственный вызов конструктора.
Missing/null не проверяются как контейнер. `required()` проверяется до любого default,
в том числе автоматического пустого typed collection. `forbidExplicitNull()` действует
до нормализации и не запрещает null, полученный из пустой строки при NullIfEmpty.
По умолчанию действует Keep; cast всего поля пропускает нормализацию пустой строки.

`DefaultSpec::value($value, ValueState ...$when)` и
`DefaultSpec::provider(HandlerSpec $provider, ValueState ...$when)` без when действуют
при Missing. Для проверки найденного значения укажите Present, для обоих состояний —
Null и Present. Provider вызывается на каждом применении и может создать независимый объект.
Literal value и `HandlerSpec::args` допускают scalar, null, unit/backed enum case и массивы
из них любой глубины; прочие объекты и closure внутри массивов запрещены.
Enum case сохраняет идентичность. [Defaults конструктора](dto.md#значения-по-умолчанию-и-изоляция-объектов)
по-прежнему вычисляются PHP только при отсутствии аргумента.

В списке порядок — each → itemCast → форма элемента. Готовый DTO от itemCast или provider
не гидратируется повторно; `cast(..., result: ...)` проверяет готовое значение.
У атрибутного Nested cast всего поля по-прежнему игнорируется.

## Discriminator

`ValueShape::variants(string $discriminator, array $map, NestedDiscriminatorMode $mode = Value,
NestedUnknownVariant $unknown = KeepRaw)` применяется только как элемент `list()`.
Enums расположены в `Brahmic\ApiSutra\Enums\DataTransfer`.

Value выбирает класс по значению пути discriminator; Key — по первому ключу обёртки
(пустой discriminator означает текущий объект). Map содержит значения/ключи и классы DTO.
Неизвестный или отсутствующий вариант обрабатывается через KeepRaw, Skip или Error.
Error даёт `unknown_nested_variant`. Ошибка известного варианта никогда не подавляется.
KeepRaw несовместим с typed collection, принимающей только DTO: это ошибка конфигурации.

## Scoped cast и provider

`HandlerSpec($class, $args)` создаёт обработчик на каждое применение поля или элемента.
Property Cast создаётся на поле, атрибутный Nested.itemCast — один раз на проход списка.
Профили и явно переданные экземпляры сохраняют прежний жизненный цикл.

Для вложенной гидратации с тем же набором реализуйте `ScopedCastInterface`
или `ScopedDefaultValueProviderInterface` из Contracts\Interfaces\Casting и
Contracts\Interfaces\DataTransfer соответственно. Пример cast для EntryDto выше:

```php
use Brahmic\ApiSutra\Contracts\Interfaces\Casting\ScopedCastInterface;
use Brahmic\ApiSutra\Serialization\Rules\HydrationScope;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class EntryCast implements ScopedCastInterface
{
    public function hydrateInScope(mixed $value, HydrationScope $scope): mixed
    {
        return $scope->hydrate($value, EntryDto::class);
    }

    public function hydrate(mixed $value, ?PipelineContext $context = null): mixed
    {
        return Hydrator::default()->hydrate($value, EntryDto::class);
    }

    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        return $value;
    }
}
```

Scope передаётся при **любой** регистрации обработчика: descriptor, атрибут, itemCast,
provider или профиль. Scoped provider реализует
`resolveInScope(mixed $value, ValueState $state, array $source, HydrationScope $scope): mixed`
и прежний `resolve(...)`. `scope->hydrateCollection($items, $class)` сохраняет набор
в помощниках вроде RowCast; `scope->context()` возвращает PipelineContext или null standalone.
Не храните scope в singleton или свойстве переиспользуемого обработчика. Вызов
`Hydrator::default()` внутри помощника — явная граница: набор туда не передаётся.

## Дополнительные поля

Рекомендуемое имя технического свойства — `_extra`. Оно задаётся явно через
`DtoRules::extras()` и не зарезервировано ядром: существующие модели с `extra`
или другим именем продолжают работать со своим объявлением.

`extras('_extra')` сохраняет данные, которые не прочитаны ядром. Receiver должен быть
public array или ?array: параметром единственного конструктора либо свойством класса
без конструктора. Static/virtual/non-public, несовместимый тип, отдельный FieldRule и
входные атрибуты receiver запрещены. Default параметра разрешён.

Учитывается только выбранный primary/fallback. Исходный null считается прочитанным.
Пересекающиеся пути объединяются: чтение родителя поглощает всё поддерево; порядок полей
на результат не влияет. Пустые прочитанные ветви удаляются; нетронутые false, 0, null,
пустые строки и массивы сохраняются. Чтение произвольных ключей provider не считается
потреблением. Ключ источника с именем receiver остаётся внутри `_extra`, а не заполняет его напрямую.

Например, для `EntryDto` из примера источник
`{"record_id": 7, "extra": {"enabled": true}, "_extra": "remote"}` даст
`id = 7` и `_extra = ['extra' => ['enabled' => true], '_extra' => 'remote']`.
Оба исходных ключа сохраняются. Если ключ прочитан правилом другого поля, он уже
считается потреблённым и в остаток не попадёт. Префикс `_` лишь отличает техническое
свойство визуально; от совпадения имён защищает этот порядок обработки.

Для проекции each соседи выбранного значения остаются у владельца списка. В примере:

```json
{
  "rows": [
    {"sourceKey": 0, "remainder": {"meta": {"revision": 2}}}
  ],
  "next_feature": null
}
```

Каждая проекция использует плотный список записей `{sourceKey, remainder}`, независимо
от пропущенных элементов. Для списка списков remainder рекурсивно является таким же
списком записей. sourceKey хранит PHP-тип ключа **после** JSON-декодирования: `"1"`
становится int 1, `"01"` остаётся строкой. Это не различает числовое имя object и индекс list.

Value-discriminator остаётся в `_extra` дочернего DTO, если не сопоставлен его полю.
В режиме Key дочерний DTO получает содержимое выбранной обёртки, её соседи остаются
в остатке списка. KeepRaw сохраняет весь неизвестный элемент; соседи each остаются
родителю. Skip поглощает весь элемент вместе с соседями each.

## Receiver в исходящих запросах

Клиент с набором исключает receiver по классу модели: и у гидратированного, и у вручную
созданного объекта. Он не отправляется под своим именем и не разворачивается в корень.
`toArray()` и DtoSerializer без набора продолжают сериализовать `_extra` как обычное свойство.

В body, BodyRoot, multipart-body и при сборке query правило действует на DtoInterface,
plain DTO, списки и публичные plain-обёртки любой допустимой глубины. Для plain DTO
сохраняется JSON остальных публичных свойств; при отсутствии свойств получается `{}`.
Ядро заменяет только контейнеры на пути к receiver, не изменяет DTO и не вызывает
конструкторы. Header/path/file по-прежнему DTO не сериализуют.

Query URL builder допускает только скаляры и плоские списки: исключение receiver
не делает DTO допустимым query-значением. Структура отклоняется до HTTP. Для такого
API опишите явные query-поля отдельной моделью запроса.

Непрозрачные преобразования имеют явные ограничения:

- Класс с receiver и DateTimeInterface, JsonSerializable, Stringable или `toArray()` без DtoInterface
  отклоняется при компиляции набора.
- Property Cast и cast по типу, получающий значение с видимым receiver, даёт
  `SerializationException` **до вызова cast и HTTP** (`serialization_error`).
  Это относится и к JsonCast на BodyRoot/Body/query.
- Обход не раскрывает JsonSerializable, Stringable, пользовательский `toArray()`,
  DateTime, enum и closure. Если такая внешняя обёртка прячет DTO с receiver,
  её представление остаётся ответственностью SDK.
- To, DateTimeTo, Query, Body, BodyRoot, Header, Path, File на receiver —
  `ConfigurationException` при компиляции.

Prepared request, requestDebug, ключ HTTP cache и лог используют представление уже без
receiver. Чтобы отправить дополнительные данные, создайте явную модель запроса.

## Диагностика и входы

При подключённом наборе `HydrationException::context()` содержит `sourcePath`,
`sourcePathKind` и `sourceCandidates`. Старый `path` описывает DTO, sourcePath —
JSON Pointer исходного значения (`~` → `~0`, `/` → `~1`, корень — пустая строка).
Например, ошибка `rows[1].value.record_id` даёт path `items[1].id` и
sourcePath `/rows/1/value/record_id`; unwrap=data добавит соответствующий префикс.

| SourcePathKind | Значение |
| --- | --- |
| Resolved | Точное расположение исходного значения |
| Expected | Отсутствующий primary; возможные пути — sourceCandidates |
| Boundary | Ближайший известный вход cast/provider/hook/computed/aggregate |
| Unavailable | Исходное расположение неизвестно; sourcePath = null |

Автоматический лог вызывает `logContext()`: объявленные сегменты и индексы настоящего
списка сохраняются, неизвестные строковые **и числовые** ключи словаря заменяются `*`.
Точный путь остаётся в исключении и ExecutionResult. Message не включает значения ответа.
После пользовательского преобразования ядро не выдаёт новый путь за исходный.
При Ready без объявленного пути continuation происхождение Unavailable.

Один гидратор клиента используется в Returns (sync/promise), пагинации, CompositeFlow,
await и cached awaitAs. При наборе items-only пагинация также применяет itemsType;
без набора сохраняет прежний raw-результат. HTTP cache хранит ответ, поэтому другой
клиент гидратирует cache hit собственными правилами. Handler с ненулевым результатом,
RawResponse и Download обходят гидратацию DTO. [Готовность await](provider-async-await.md)
определяется раньше строгой проверки финального DTO.

Глубина ограничена 512 активными узлами DTO/list, корень считается первым.
Соседние ветви не суммируются. Более глубокий ввод даёт `hydration_depth_exceeded`,
циклическая ссылка PHP-объектов — `cyclic_hydration_input`. Один объект в независимых
ветках допустим. Состояние обхода принадлежит одному корневому вызову и не кешируется.

## Переход существующего SDK

Рабочие атрибутные рецепты остаются доступны: [provider для Present/Null](attributes/data-transfer.md#provider-для-найденного-значения)
проверяет запрет null и форму до Nested; `Nested(itemCast:)` может проверить scalar-элемент
или вернуть raw-объект через фабрику. Такой itemCast создаётся без аргументов, поэтому
параметризованный строгий scalar cast требует отдельного класса. Bare array и PHPDoc
не дают проверки элементов. `list(list(dto(...)))` заменяет RowCast для двумерного списка
без отдельного DTO ряда. Raw-фабрика, напрямую создающая объект, гидратор не вызывает.

При переносе на набор удалите конфликтующие входные атрибуты только у полей с FieldRule.
Рекурсивные вызовы Hydrator внутри casts/providers переводите на scope; простой вызов
raw-фабрики менять не требуется. Не включайте strict до проверки реальных типов JSON.
Исходящую модель с receiver пересмотрите отдельно: `_extra` больше не отправляется
клиентом с набором, а cast всего объекта с receiver запрещён. Без набора эти изменения
не применяются. Общая [миграция версии](migration.md) и исправления
[continuation](provider-async-await.md#миграция-с-эвристического-ожидания) описаны отдельно.
