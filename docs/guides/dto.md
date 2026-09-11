# DTO

Краткий гайд по DTO, маппингу и валидации.

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

Автоматика резолвит DTO contract по **иерархии конкретного DTO-класса**:
- через `DtoHydrationProfile` / `DtoSerializationProfile`
- через class-level override `DtoHydrate` / `DtoSerialize`
- через property-level override
- и, если ничего не задано, через zero-config defaults

Это означает:
- в рамках одного SDK может быть не один `BaseDto`, а несколько веток DTO с разными правилами
- разные DTO-иерархии внутри одного клиента могут иметь разные profiles
- главное, чтобы источник истины оставался в DTO/profile layer, а не в runtime-клиенте

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
```php
use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;

#[Nested(type: AddressDto::class)]
public AddressDto $address;
```

Параметры `Nested`:
- `type` — тип элемента (для массивов/коллекций)
- `itemCast` — cast для каждого элемента массива перед hydration/type stage
- `from` — путь к данным (dot‑notation)
- `fallback` — альтернативные пути
- `each` — путь внутри каждого элемента массива
- `discriminator` + `map` — полиморфная гидрация
- `discriminatorMode` — режим discriminator: `Value` (значение поля) или `Key` (имя ключа)
- `unknownVariant` — поведение при неизвестном варианте: `KeepRaw`, `Skip`, `Error`

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
1. `#[Cast(...)]`
2. `#[EmptyStringAsNull(...)]`
3. hydration profile-level `emptyStringBehavior`
4. дефолтное поведение `Keep`

### Практические правила
- default поведение ядра не меняется: `''` остаётся `''`
- `EmptyStringAsNull` полезен в основном для `?string`
- если `'' -> null` включено для non-nullable поля и после `DefaultValue` значение всё ещё `null`, hydrator бросает `ConfigurationException`
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
