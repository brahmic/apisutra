# Serialization

Настройки сериализации и маппинга.

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

## Casts
```php
use Brahmic\ApiSutra\Casts\DateTimeCast;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    casts: [
        'datetime' => DateTimeCast::class,
    ],
);
```

Если касты не заданы — используются только встроенные и атрибутные.

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
