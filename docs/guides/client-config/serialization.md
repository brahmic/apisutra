# Serialization

Настройки сериализации и маппинга.

Проверка обязательных полей DTO и типов, строгий JsonCast и безопасные ошибки
гидратации работают автоматически. Отдельный strict/required-флаг не нужен;
[nullable/default определяются объявлением DTO](../dto.md#обязательные-поля-и-ошибки-гидратации).
Профили дат и пользовательские casts нужны для особенностей формата провайдера.
[Исходный ответ для диагностики](../errors.md#подробная-диагностика-гидратации)
доступен и при выключенном debug.

## NamingStrategy
```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\NamingStrategy;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    namingStrategy: NamingStrategy::SnakeCase,
);
```

По умолчанию `NamingStrategy::None` (имена не преобразуются).
Меняйте стратегию, если API использует другую схему именования для request/query/header/path
или как hydration fallback. Для body DTO каноническая naming policy рекомендуется через
`DtoSerializationProfile`.

## QueryArrayFormat
```php
use Brahmic\ApiSutra\Enums\Http\QueryArrayFormat;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    queryArrayFormat: QueryArrayFormat::Brackets,
);
```

По умолчанию используется `QueryArrayFormat::Brackets`.
Меняйте формат, если провайдер ожидает `comma` или `repeat`.

## Текстовый boolean

`textBooleanFormat` необязателен: `BooleanFormat::Numeric` по умолчанию передаёт
true как `1`, false как `0` в query и скалярных полях multipart.
Для API, ожидающего слова, настройте клиент:

```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Serialization\BooleanFormat;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    textBooleanFormat: BooleanFormat::Literal,
);
```

Точечное переопределение — `#[Cast(BooleanCast::class, BooleanFormat::Numeric)]`
или Literal; см. [BooleanCast](../casts.md#boolean-в-текстовых-полях).
Сначала применяется cast, затем формат оставшегося boolean. Строки cast не
переинтерпретируются. Настройка не меняет JSON, DTO, path или заголовки.

## Casts

Объектные аргументы `#[Cast]` изолированы между операциями независимо от environment;
см. [правила вычисления атрибутов](../casts.md#объектные-аргументы-атрибутов).
Готовые экземпляры, явно переданные через `casts`, сохраняют прежнюю идентичность.

`casts` — правила по PHP-типу для сериализации свойств запроса:

```php
use Brahmic\ApiSutra\Casts\DateTimeCast;
use Brahmic\ApiSutra\Config\ClientConfig;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    casts: [
        DateTimeImmutable::class => DateTimeCast::class,
    ],
);
```

Ключ — объявленный PHP-тип, например `DateTimeImmutable::class` или `'string'`.
Гидратация ответа через `Returns` не использует эту настройку: ей нужны
`DtoHydrationProfile` или `#[Cast]` свойства. Источники и приоритеты приведены
в [справке casts](../casts.md#регистрация-кастов).

## Request DateTime
```php
use Brahmic\ApiSutra\Config\DateTimeSerializationPolicy;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    requestDateTime: new DateTimeSerializationPolicy(
        format: DATE_ATOM,
        timezone: null,
    ),
);
```

- `requestDateTime` применяется к request-level serialization:
  - `query`
  - `header`
  - `path`
  - request body fields, которые не проходят через DTO body serializer
- `timezone` приводит дату к указанной зоне перед форматированием
- DTO hydration/body semantics не задаются через `ClientConfig`

Для union‑полей (`string|DateTimeInterface`) ветка выбирается по runtime‑значению,
а не по порядку типов в объявлении свойства.

Сохранение больших целочисленных JSON-идентификаторов включено автоматически.
Параметров ClientConfig для этого не требуется: DTO провайдера выбирает string
или int|string; переполнение int даёт ошибку. Контракт и миграция —
[большие целые в ответах](../serialization.md#большие-целые-в-ответах).

Подробное описание поведения: [Сериализация запросов](../serialization.md).

## DtoSerializationProfile

**Настоятельная рекомендация:** централизуйте SDK DX serialization semantics через
`DtoSerializationProfile`, а wire body semantics — через отдельную transport policy в `ClientConfig`.

Рекомендуемый default для provider SDK:
- `enumOutput: EnumOutput::TitleValueString`
- `strictEnums: false`
- `serializeNulls: false`
- `namingStrategy`: по body DTO контракту провайдера, часто `SnakeCase`

Это даёт:
- канонический `toArray()`
- централизованную настройку через `BaseDto` / `BaseResponseDto`
- мягкий fallback, даже если у enum ещё нет `title()`

```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\DtoSerializationPolicy;
use Brahmic\ApiSutra\Contracts\Interfaces\Serialization\DtoSerializationProfileInterface;
use Brahmic\ApiSutra\Enums\Configuration\NamingStrategy;
use Brahmic\ApiSutra\Enums\Serialization\EnumOutput;

final readonly class ProviderDtoSerializationProfile implements DtoSerializationProfileInterface
{
    public function policy(): DtoSerializationPolicy
    {
        return new DtoSerializationPolicy(
            enumOutput: EnumOutput::TitleValueString,
            strictEnums: false,
            namingStrategy: NamingStrategy::SnakeCase,
            serializeNulls: false,
        );
    }

    public function casts(): array
    {
        return [];
    }
}

$dtoProfile = new ProviderDtoSerializationProfile();

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    dtoSerializationProfile: $dtoProfile,
    wireBodySerializationPolicy: new DtoSerializationPolicy(
        enumOutput: EnumOutput::Value,
        strictEnums: false,
        namingStrategy: NamingStrategy::SnakeCase,
        serializeNulls: false,
    ),
    requestPartsEnumOutput: EnumOutput::Value,
);
```

- `dtoSerializationProfile` задаёт DX / `toArray()` semantics
- `wireBodySerializationPolicy` задаёт outbound body semantics
- request-level enum policy задаётся отдельно
- один и тот же профиль рекомендуется привязывать к `BaseDto` / `BaseResponseDto`

## Request-level enum serialization

Для query/header/path оставляйте enum policy в клиенте.

```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Serialization\EnumOutput;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    dtoSerializationProfile: $dtoProfile,
    wireBodySerializationPolicy: new DtoSerializationPolicy(enumOutput: EnumOutput::Value),
    requestPartsEnumOutput: EnumOutput::Value,
);
```

Рекомендация:
- DX / `toArray()` → `DtoSerializationProfile`
- wire body → `wireBodySerializationPolicy`
- query/header/path → request-level config

Подробное: [Сериализация запросов](../serialization.md).

## Где детали
- [Сериализация запросов](../serialization.md)
- [Naming Strategy](../naming-strategy.md)
- [Casts](../casts.md)
