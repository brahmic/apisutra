# Представление DTO: DX и wire

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

Альтернатива для входящих данных — [внешний набор правил](../../guides/dto/plain-models.md),
переданный клиенту или `Hydrator::forRules()`. Он позволяет оставить классы без
атрибутов и базовых классов ApiSutra. Не совмещайте `DtoRules` и профиль гидратации
на одном классе; исходящий профиль сериализации настраивается отдельно.

Поэтому:
- если у SDK есть один общий `BaseDto`, binding на нём обычно самый удобный
- если у SDK несколько независимых DTO-веток, profiles можно развешивать по соответствующим base classes или конкретным DTO

## DTO‑сериализация для body
Если значение свойства — DTO, SDK сериализует его:
- только **публичные** свойства
- `#[To]` для переименования
- dot‑paths в `#[To]` поддерживаются
- `Cast` и registry‑касты применяются до сериализации
- для union‑типов (`A|B`) ветка выбирается по runtime‑значению, а не по порядку в type-hint

## DateTime‑сериализация
DateTime semantics после унификации разделены по слоям:

- **DTO hydration** — через `DtoHydrationProfile` / `DtoHydrate` / `DateTimeFrom`
- **DTO DX serialization** — через `DtoSerializationProfile` / `DtoSerialize` / `DateTimeTo`
- **request-level serialization** (`query/header/path` и request body fields) — через `ClientConfig::requestDateTime`
- **wire body serialization** — через `ClientConfig::wireBodySerializationPolicy`

### Parse (гидрация)

Входной формат, timezone, offset и реакция на неверную дату описаны в
[контракте гидратации](../dto/profiles.md#разбор-входной-даты).

### Serialize
Для DTO DX serialization правила берутся из `DateTimeSerializationPolicy`:
- `format` — формат выходной строки
- `timezone` — если задана, дата приводится к этой зоне перед форматированием
- дефолтный path форматирует только `DateTimeInterface`
- строка не перепарсивается автоматически как дата при DTO serialization

Для request-level сериализации используется `ClientConfig::requestDateTime`.
Для outbound body по умолчанию используется transport-level wire policy.

### Приоритеты

Пользовательский Cast и cast по типу выбираются по [общему контракту casts](casts.md#приоритет-применения).
Когда используется встроенное форматирование даты, DateTimeTo поля переопределяет
DateTimeSerializationPolicy. В DX базу задаёт профиль DTO, в wire — политика тела.
Кастомный cast может сам определять формат и не обязан читать эту политику.

### Union типы и выбор ветки
Для union‑полей SDK сначала пытается выбрать тип, совпадающий с runtime‑значением.
Это убирает зависимость от порядка типов в объявлении:

- `DateTimeInterface|string` со строковым значением обрабатывается как строка
- `string|DateTimeInterface` с объектом даты обрабатывается как `DateTimeInterface`

Если ни одна ветка union не совпала по runtime, используется первый non-null тип
(поведение совместимо с прежним fallback по выбору ветки, но без скрытого string reparsing на serialize).

## Enum-сериализация

После zero-config DX/wire separation сериализация рассматривается как разные слои:
- **DX / `toArray()`** → через `DtoSerializationProfile`
- **wire body** → через `ClientConfig::wireBodySerializationPolicy`
- **query/header/path** → через request-level client config

Это важно по двум причинам:
- `toArray()` может быть удобнее для разработчика SDK, чем wire payload
- default wire режим не должен ломать provider contract

### Рекомендуемый default для DX DTO
Для provider SDK как DX default рекомендуется:
- `enumOutput: EnumOutput::TitleValueString`
- `strictEnums: false`

Это даёт формат `title|value`, а при отсутствии `title()` использует безопасный fallback.

### Рекомендуемый default для wire body
Для transport wire default рекомендуется:
- `enumOutput: EnumOutput::Value`
- `strictEnums: false`

### Опции и режимы
Доступные режимы `EnumOutput`:
- `Value` — `BackedEnum` → `value`, обычный enum → `name`
- `Name` — всегда `name`
- `Object` — `{value, title}` (только для **body**)
- `TitleValueString` — строка `title|value` (подходит для query/header/path)

### title() и strictEnums
Если выбран `Object` или `TitleValueString`, SDK ищет метод `title()` у enum:
- `strictEnums=true` → отсутствие `title()` или неверный тип возвращаемого значения
  приводит к `ConfigurationException`
- `strictEnums=false` → используется fallback:
  - `Object`: `title` = `value`
  - `TitleValueString`: `value|value`

`title()` должен возвращать человекочитаемое название **текущего** значения enum.
Допустимые типы: `string` или `Stringable`.

### Приоритеты и совместимость
Приоритет сериализации для enum:
1) `#[Cast]` на свойстве
2) registry‑каст по типу свойства
3) enum‑сериализация по effective policy текущего слоя

Где effective policy берётся:
- DX / `toArray()` → `DtoSerializationProfile`
- wire body → `ClientConfig::wireBodySerializationPolicy`
- query/header/path → request-level config

Для query/header/path допускаются **только скаляры**. Режим `Object` там не используется.

### Мини‑пример
```php
enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function title(): string
    {
        return match ($this) {
            self::Active => 'Активен',
            self::Inactive => 'Неактивен',
        };
    }
}
```

### Где настроить
См. [ClientConfig: Serialization](request-parts.md) — там показано разделение
body DTO profile и request-level enum policy.
