# DTO

Краткий гайд по DTO, маппингу и валидации.

Для моделей без атрибутов используйте [внешний набор правил](hydration-rules.md):
он задаёт mapping, дочерние DTO, strict, extras и проверку присутствия.
`DTO::from()` не наследует набор клиента; standalone-вход с набором — `Hydrator::forRules()`.

## Каноническая сериализация DTO
`toArray()` — каноническая DX-сериализация DTO.

Это означает:
- `toArray()` описывает SDK-friendly DTO output, а не обязательно реальный wire payload
- DX DTO semantics централизуются через `DtoSerializationProfile`
- outbound body по умолчанию подчиняется transport-level wire policy
- request/query/header/path semantics живут отдельно в `ClientConfig`
- safe wire default задаётся через `ClientConfig::wireBodySerializationPolicy`

Рекомендуемый путь для provider SDK:
- создать `DtoSerializationProfile`
- привязать его к `BaseDto` / `BaseResponseDto`
- при необходимости явно выровнять wire и DX через `ClientConfig::wireBodySerializationPolicy`

### Важное уточнение
Binding на `BaseDto` — это **рекомендуемый carrier**, но не обязательный единственный вариант.

В атрибутной модели автоматика резолвит DTO contract по **иерархии конкретного DTO-класса**:
- через `DtoHydrationProfile` / `DtoSerializationProfile`
- через class-level override `DtoHydrate` / `DtoSerialize`
- через property-level override
- и, если ничего не задано, через zero-config defaults

Это означает:
- в рамках одного SDK может быть не один `BaseDto`, а несколько веток DTO с разными правилами
- разные DTO-иерархии внутри одного клиента могут иметь разные profiles
- источник истины для этой модели — DTO и его профиль

Альтернатива для входящих данных — [внешний набор правил](hydration-rules.md),
переданный клиенту или `Hydrator::forRules()`. Он позволяет оставить классы без
атрибутов и базовых классов ApiSutra. Не совмещайте `DtoRules` и профиль гидратации
на одном классе; исходящий профиль сериализации настраивается отдельно.

Поэтому:
- если у SDK есть один общий `BaseDto`, binding на нём обычно самый удобный
- если у SDK несколько независимых DTO-веток, profiles можно развешивать по соответствующим base classes или конкретным DTO

## Базовый DTO
```php
use Brahmic\ApiSutra\DataTransfer\AbstractResponseDto;

final readonly class UserDto extends AbstractResponseDto
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
```

## Автокаст по declared type
При гидрации DTO ядро умеет делать **safe scalar auto-cast** по объявленному типу свойства.

Типовые кейсы:
- `"12"` -> `12` для `int`
- `"12.5"` -> `12.5` для `float`
- `"true"` / `"false"` / `"1"` / `"0"` -> `bool`
- `42` -> `"42"` для `string`

Это работает как встроенный fallback и не заменяет `#[Cast]`.
Если формат провайдера нестандартный или нужна особая логика — используйте явный `#[Cast(...)]`.

## Inheritance и hydration
Hydrator в `apisutra` использует модель `constructor-first`, но теперь поддерживает и inherited public properties вне constructor chain.

Это означает:
- всё, что покрывается effective constructor chain, инициализируется через конструктор
- оставшиеся публичные гидрируемые свойства могут быть доинициализированы hydrator-ом напрямую
- это особенно полезно для DTO-иерархий, где базовый класс держит общие поля, а конечный DTO добавляет свои блоки данных

Пример:
```php
abstract readonly class BaseBlockDto extends AbstractResponseDto
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

Практические правила:
- constructor-first остаётся основным и рекомендуемым контрактом
- fallback assignment работает только для оставшихся public data properties
- для nullable non-constructor property при `Missing` hydrator инициализирует `null`
- для non-nullable missing non-constructor property hydrator бросает явную ошибку

## Значения по умолчанию и изоляция объектов

Если значение параметра конструктора не разрешено из входных данных, атрибутов или
автодефолта коллекции, гидратор опускает аргумент. PHP вычисляет его default при
единственном вызове конструктора. `new` и массивы с объектами в default создают
независимые значения для каждого DTO, включая элементы коллекций и вложенные DTO.
Если значение разрешено, в том числе допустимый null, default не вычисляется.
Исключение из выражения default выходит так же, как исключение конструктора DTO.

Это одинаково для `DTO::from()`, прямого Hydrator, Returns, пагинации, CompositeFlow и await,
при включённом и выключенном кеше метаданных. В `#[DefaultValue(value: [new ...])]`
объекты также создаются заново для каждого гидрируемого DTO.
Правила вычисления объектных аргументов атрибутов описаны в
[справке casts](casts.md#объектные-аргументы-атрибутов).

Объекты, которые вызывающий код передал во входных данных, сохраняют свою идентичность:
гидратор не копирует их автоматически. Намеренно общие экземпляры casts профиля и
статическое состояние пользовательских обработчиков также не изолируются.

## Обязательные поля и ошибки гидратации

Без внешних правил обязательность следует из PHP-типа и объявления DTO.
Приведённые ниже проверки применяются после `From`/fallback,
нормализации пустой строки, `DefaultValue` и автодефолта typed collections.
Внешний `FieldRule::required()` дополнительно требует наличия ключа **до** defaults,
а `forbidExplicitNull()` запрещает исходный null даже для nullable-типа;
см. [формы и присутствие](hydration-rules.md#формы-присутствие-и-defaults).

| Объявление и вход | Результат |
| --- | --- |
| Параметр конструктора с default, поле отсутствует | Значение constructor default. |
| Параметр конструктора без default, поле отсутствует, в том числе `?T` | `required_field_missing`. Nullable разрешает null, но не делает аргумент необязательным. |
| Nullable public property вне constructor chain, без default, поле отсутствует | Null. |
| Non-nullable public property вне constructor chain, без default, поле отсутствует | `required_field_missing`. |
| Найден explicit null | Принимается nullable/mixed; constructor default и fallback его не заменяют. Применимый `DefaultValue` для Null может задать замену. |
| После преобразования null, а тип не допускает null | `null_not_allowed`. |
| После допустимых casts значение не подходит типу | `invalid_field_type`; scalar вместо вложенного DTO — `unexpected_response_shape`. |

Конструктор вызывается один раз; проверяется тип его параметра, даже если он
преобразует значение для свойства другого типа. В режиме Legacy сохраняются scalar
conversions и выбор union-веток. Внешний набор может включить
[Strict](hydration-rules.md#policy-и-строгие-типы), в том числе для результатов casts.

Прямой `DTO::from()` выдаёт `HydrationException` с `reason`, `path`, `expected`,
`actual`. В запросе это `hydration_error` с сохранённым HTTP-ответом. Путь содержит
имена свойств DTO и порядковые индексы (`items[1].id`), при наличии unwrap — его
префикс. Это не обязательно буквальный путь `From` внутри ответа.

Неверные объявления классов, casts, карт `Nested`, timezone и конфликты readonly
инициализации остаются ошибками конфигурации. Произвольная ошибка пользовательского
конструктора или computed не классифицируется как ошибка конкретного поля.

При миграции обновите обработку прежних `ArgumentCountError`/`TypeError` и
`ConfigurationException` для перечисленных ошибок данных. Успешные defaults/null
сценарии сохраняются. Строгий вложенный JSON описан в [JsonCast](casts.md#jsoncast),
доступ к исходному ответу — в [диагностике](errors.md#подробная-диагностика-гидратации).

## Маппинг полей
Когда ключи ответа/запроса отличаются от имени свойства.
- `Map` — когда нужен один и тот же внешний ключ и для гидрации, и для сериализации.
- `From` — для входящих данных (ответ API).
- `To` — для исходящих данных (сериализация запроса).
```php
use Brahmic\ApiSutra\Attributes\DataTransfer\Map;
use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\Attributes\DataTransfer\To;

#[Map('user_id')]
public int $userId;

#[From('data.user_id')]
public int $id;

#[To('user_id')]
public int $id;
```

### Что использовать

| Сценарий | Что использовать | Почему |
|---|---|---|
| Один и тот же внешний ключ нужен и на вход, и на выход | `#[Map('user_id')]` | Убирает дублирование `From` + `To` |
| Нужен только входящий mapping | `#[From('data.user_id')]` | Поддерживает dot-path и fallback |
| Нужен только исходящий mapping | `#[To('user_id')]` | Явно управляет сериализацией |
| Вход и выход отличаются | `#[From(...)]` + `#[To(...)]` | Направления независимы |
| Есть `Map`, но одно направление надо переопределить | `Map` + `From` или `Map` + `To` | `From` имеет приоритет при hydrate, `To` — при serialize |
| Обычный camelCase <-> snake_case без исключений | `NamingStrategy::SnakeCase` | Не нужны явные атрибуты |

Приоритеты:
- hydrate: `From` -> `Map` -> `NamingStrategy`
- serialize: `To` -> `Map` -> `NamingStrategy`

## Вложенные DTO
Когда ответ содержит вложенные объекты или списки объектов (например, `user.address`, `order.items[]`).

Одиночный объект может быть plain DTO без базового класса ApiSutra:

```php
<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;
use Brahmic\ApiSutra\Serialization\Hydrator;

final readonly class AddressDto
{
    public function __construct(public string $city)
    {
    }
}

final readonly class UserDto
{
    #[Nested(type: AddressDto::class)]
    public AddressDto $address;
}

$payload = json_decode('{"address":{"city":"Sample"}}', true, flags: JSON_THROW_ON_ERROR);
$user = Hydrator::default()->hydrate($payload, UserDto::class);
echo $user->address->city; // Sample
```

То же объявление работает с constructor promotion и через `Returns`.
Для одиночного свойства конкретный класс можно вывести из native-типа:
`#[Nested] public AddressDto $address`. Для массива `type` задаёт класс элемента.
Правила выбора объекта/списка, nullable/union и все параметры —
в [справочнике Nested](attributes/data-transfer.md#nested).

Пример key‑mode (кейс вида `{"person": {...}}`):
```php
use Brahmic\ApiSutra\Enums\DataTransfer\NestedDiscriminatorMode;
use Brahmic\ApiSutra\Enums\DataTransfer\NestedUnknownVariant;

#[Nested(
    discriminatorMode: NestedDiscriminatorMode::Key,
    map: [
        'person' => PersonOwnerDto::class,
        'organization' => OrganizationOwnerDto::class,
    ],
    unknownVariant: NestedUnknownVariant::KeepRaw,
)]
public array $owners = [];
```

Если nested-массив уже найден правильно, но каждый его элемент нужно отдельно преобразовать, используйте `itemCast`:

```php
use Brahmic\ApiSutra\Casts\DataUriBase64FileCast;
use Brahmic\ApiSutra\VO\Files\Base64File;

#[Nested(
    type: Base64File::class,
    itemCast: DataUriBase64FileCast::class,
)]
public array $faces = [];
```

Правило:
- `Cast` на свойстве — transform всего значения свойства
- `Nested(itemCast: ...)` — transform каждого элемента массива

## Коллекции
Если хотите получить типизированную коллекцию вместо массива — используйте
свойство‑коллекцию и `#[Nested]`:
```php
use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;
use Brahmic\ApiSutra\Collections\AbstractTypedCollection;

final readonly class OrderItemCollection extends AbstractTypedCollection
{
    protected static function itemClass(): string
    {
        return OrderItemDto::class;
    }
}

final readonly class OrderDto extends AbstractResponseDto
{
    public function __construct(
        #[Nested(type: OrderItemDto::class)]
        public OrderItemCollection $items,
    ) {}
}
```

Для **non-nullable typed collection** ядро автоматически подставляет пустую коллекцию,
если поле **отсутствует** (`ValueState::Missing`).

Это правило:
- работает только для `AbstractTypedCollection`
- работает только для **missing**
- не срабатывает для `null`
- не срабатывает для nullable-свойства (`?OrderItemCollection`)
- не перебивает `#[DefaultValue]`, если `DefaultValue` сам покрывает `Missing`

Если вам нужно поведение `null -> []`, оставляйте явный
`#[DefaultValue(value: [], when: [ValueState::Null])]`
или комбинированный `Missing + Null`.

Если `DefaultValue` покрывает только `Null`, built-in fallback для `Missing`
продолжит работать.

## Cast, DateTimeFrom/To и DefaultValue
Когда нужно преобразовать тип или задать дефолт при отсутствии/NULL.
```php
use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\DataTransfer\DateTimeFrom;
use Brahmic\ApiSutra\Attributes\DataTransfer\DateTimeTo;
use Brahmic\ApiSutra\Attributes\DataTransfer\DefaultValue;
use Brahmic\ApiSutra\Casts\DateTimeCast;

#[DateTimeFrom(format: DATE_ATOM, defaultTimezone: 'UTC')]
#[DateTimeTo(format: DATE_ATOM, timezone: 'UTC')]
public DateTimeImmutable $createdAt;

#[Cast(DateTimeCast::class, format: 'Y-m-d')]
public DateTimeImmutable $legacyCreatedAt;

#[DefaultValue('unknown')]
public string $status;
```

## Empty string normalization
По умолчанию `apisutra` не считает `''` эквивалентом `null`:

- `Missing` — ключ отсутствует
- `Null` — ключ есть, значение `null`
- `Present` — значение найдено, включая `''`

Если провайдер использует пустую строку как "значения нет", можно включить это поведение явно.

### Точечно на свойстве
```php
use Brahmic\ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;

#[EmptyStringAsNull]
public ?string $middleName = null;
```

Если нужен режим и для blank strings:

```php
#[EmptyStringAsNull(blank: true)]
public ?string $comment = null;
```

### Централизованно через hydration profile
```php
use Brahmic\ApiSutra\Config\DtoHydrationPolicy;
use Brahmic\ApiSutra\Contracts\Interfaces\Serialization\DtoHydrationProfileInterface;
use Brahmic\ApiSutra\Enums\DataTransfer\EmptyStringBehavior;

final readonly class ProviderDtoHydrationProfile implements DtoHydrationProfileInterface
{
    public function policy(): DtoHydrationPolicy
    {
        return new DtoHydrationPolicy(
            emptyStringBehavior: EmptyStringBehavior::NullIfEmpty,
        );
    }

    public function casts(): array
    {
        return [];
    }
}
```

### Приоритеты

Для атрибутной модели без внешнего набора:

1. `#[Cast(...)]`
2. `#[EmptyStringAsNull(...)]`
3. hydration profile-level `emptyStringBehavior`
4. дефолтное поведение `Keep`

Во внешнем наборе нормализация задаётся через `RulePolicy::emptyString`;
[приоритеты и порядок обработки](hydration-rules.md#формы-присутствие-и-defaults).

### Практические правила
- default поведение ядра не меняется: `''` остаётся `''`
- `EmptyStringAsNull` полезен в основном для `?string`
- если `'' -> null` включено для non-nullable поля и после `DefaultValue` значение всё ещё `null`, hydrator бросает `HydrationException` с reason `null_not_allowed`
- `DefaultValue(... when: [Null])` совместим с этой нормализацией: после `'' -> null` будет работать как для обычного `null`

`DefaultValue` может задавать значение по условию `ValueState`:
- `Missing` — ключ отсутствует
- `Null` — ключ есть, но значение null
- `Present` — значение найдено

Также можно использовать провайдера, если нужен контекст
(например, request/meta/traceId) или более сложная логика:
```php
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use Brahmic\ApiSutra\Enums\DataTransfer\ValueState;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final class StatusDefault implements DefaultValueProviderInterface
{
    public function resolve(mixed $value, ValueState $state, array $source, ?PipelineContext $context): mixed
    {
        return 'unknown';
    }
}
```

Если достаточно простого значения — используйте `#[DefaultValue]`.

Provider с `when: [ValueState::Present]` может проверить найденное значение
и вернуть его перед `Nested`. Чтобы одновременно запретить null и проверить
форму списка, используйте один provider с `when: [ValueState::Null, ValueState::Present]`.
Рабочий пример и порядок нормализации — в
[справочнике DefaultValue](attributes/data-transfer.md#defaultvalue).

Для typed collections `#[DefaultValue(value: [], when: [ValueState::Missing])]`
обычно больше не нужен: `missing -> empty collection` теперь покрывается ядром.
Явный `DefaultValue` оставляйте, если нужно:
- обработать `null`
- переопределить fallback
- зафиксировать поведение явно в контракте DTO

## Валидация
Когда нужно локально проверить DTO и получить читаемые ошибки.
```php
use Brahmic\ApiSutra\Attributes\DataTransfer\Label;
use Brahmic\ApiSutra\Attributes\DataTransfer\Validate;

#[Label('Email')]
#[Validate('required|email', message: 'Некорректный email')]
public string $email;
```

## computed() для Response DTO
`AbstractResponseDto` позволяет модифицировать данные до гидрации:
```php
final readonly class UserDto extends AbstractResponseDto
{
    public static function computed(array $data, ?PipelineContext $context = null): array
    {
        $data['fullName'] = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
        return $data;
    }
}
```

## Где детали
- Полный перечень атрибутов: `docs/guides/attributes/data-transfer.md`
- Механизм атрибутов: `docs/technical/attributes.md`
