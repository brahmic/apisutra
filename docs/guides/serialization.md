# Сериализация запросов

Этот раздел объясняет, как SDK собирает `PreparedRequest`: какие поля идут
в path/query/body, как применяется naming strategy и касты.

## Порядок этапов
Перед сериализацией SDK выполняет два шага валидации:
1. стандартная валидация request-атрибутов (`Validate`);
2. контрактная валидация oneOf/discriminator (`RequestOneOf`, `RequestDiscriminator`).

Если контракт нарушен, запрос завершается с `ErrorCode::RequestContractViolation`
до подготовки `PreparedRequest` и до HTTP-вызова.

После этого внутри `Serializer` этапы идут так:
1. `RequestPartsCollector` собирает `query/body/files/headers/placeholders`;
2. применяется enrichment (`credentialsConfig` и `requestEnrichers`);
3. `FilePayloadPreparer` финализирует `body/stream/headers`;
4. собирается `PreparedRequest`.

### Content-Type для JSON body
Для JSON body (default case и Base64) SDK выставляет `Content-Type: application/json` по умолчанию,
если заголовок ещё не задан.
Multipart и Binary задают свой Content-Type (boundary, mime-type).
Переопределить можно через `#[Header('Content-Type')]` или request enricher.

`RequestOneOf` поддерживает top-level поля и dot-path для вложенных структур.
Режим валидации задаётся через `OneOfMode` (`ExactlyOne` или `AtLeastOne`).

## Request parts enrichment (до finalize payload)
Enrichment работает с `RequestPartsBag` (не с raw JSON-строкой), поэтому
подходит для `body/query/multipart form` и не ломает общий пайплайн.

Встроенный `CredentialsEnricher` включается через `ClientConfig::credentialsConfig`.

### Merge-policy
Приоритеты:
1. runtime overrides (`withCredentials...`)
2. явные поля request
3. provider defaults (`credentialsConfig`)

Merge-режимы:
- `fill-missing` (по умолчанию) — только заполняет отсутствующие ключи
- `overwrite` — перезаписывает конфликтующие значения
- `fail-on-conflict` — бросает `ConfigurationException` при конфликте

### Scope и исключения
- scope берётся из runtime (`withCredentialsScope()`), иначе из `AuthScope`
- `#[SkipCredentialsEnrichment]` выключает enrichment на request-классе
- runtime `withCredentialsEnrichment(true/false)` имеет приоритет

### Ограничения MVP
- `BodyRoot` не обогащается (чтобы не ломать root‑payload контракты)
- `form`-defaults применяются только к multipart text fields
- файлы (`FileInput`) enrichment не модифицирует

## Базовые правила (convention)
Для каждого свойства запроса:
- `#[Path]` или плейсхолдер `{name}` в endpoint → **path**
- `#[Header]` → **headers**
- `#[File]` → **files**
- `#[BodyRoot]` → **весь body целиком**
- `#[Body]` → **body**
- `#[Query]` → **query**
- без property-атрибута:
  - если есть class-level `RequestDefaults` — используется его `unmapped`
  - иначе:
  - `GET/DELETE` → **query**
  - остальные методы → **body**

Приоритеты:
1. `#[Ignore]`
2. property-атрибуты (`Path`, `Header`, `File`, `Query`, `BodyRoot`, `Body`)
3. class-level `RequestDefaults`
4. convention по HTTP-методу

Ограничение: `BodyRoot` нельзя смешивать с `Body` и с полями, которые попадают в body по умолчанию.
Иначе будет `ConfigurationException`.

## Query
```php
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Enums\Http\QueryArrayFormat;

#[Query('q')]
public string $query;

#[Query('ids', arrayFormat: QueryArrayFormat::Comma)]
public array $ids;
```

Нюансы:
- `arrayFormat` задаёт формат массива
- `nullable` в `#[Query]` управляет включением `null`
- глобально `serializeNulls` действует для body и query

### Формат массивов в query
`QueryArrayFormat` относится **только** к query‑строке и формирует
`a[]=1&a[]=2`, `a=1,2,3` и т.п. JSON‑строку он не создаёт.

```php
#[Query('regions', arrayFormat: QueryArrayFormat::Comma)]
public array $regions;
```

### JSON‑строка вместо массива
Если API ожидает строку вида `"[1,2,3]"` (в query или body), используйте `JsonCast`
или храните строку вручную:
```php
use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Casts\JsonCast;

#[Query('regions')]
#[Cast(JsonCast::class)]
public array $regions = [1, 2, 3];

// или строка вручную
#[Query('regions')]
public string $regions = '[1,2,3]';
```

## Body и nested
```php
use Brahmic\ApiSutra\Attributes\Request\Body;

#[Body('payload.user')]
public array $user;
```

`nested` поддерживает dot‑paths. Если `nested` пустой, используется имя поля.

## Root body (JSON Patch / bulk)
```php
use Brahmic\ApiSutra\Attributes\Request\BodyRoot;

#[BodyRoot]
public array $operations; // [{...}, {...}]
```

В этом режиме значение свойства становится корнем body (например `[...]`), а не оборачивается в объект.

## Path
```php
use Brahmic\ApiSutra\Attributes\Request\Path;

#[Path('id')]
public int $userId;
```

Если имя свойства совпадает с placeholder в endpoint, `#[Path]` не обязателен:
`{id}` автоматически свяжется с `$id`.
Атрибут нужен, когда имена различаются.

## Как выбирается endpoint и baseUrl
**Endpoint:**
1) `resolveEndpoint()` в запросе (если переопределён)  
2) атрибут `#[Get('/path')]` и др.

**BaseUrl:**
1) runtime override `withBaseUrl()`  
2) `resolveBaseUrl()` запроса  
3) `ClientConfig::baseUrl`

Обычно достаточно `ClientConfig::baseUrl`.
Переопределение нужно, если:
- один клиент ходит на несколько доменов/версий API
- нужен временный override в тестах или для отдельных эндпоинтов

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
Поля `DateTimeInterface` парсятся по правилам hydration policy:
- `format` — приоритетный формат для `createFromFormat`
- если `format` не подошёл:
  - `strictFormat=true` → ошибка (без fallback)
  - `strictFormat=false` → fallback на `DateTimeImmutable($value, $defaultTimezone)`
- `defaultTimezone` используется **только** если вход без оффсета
- `preserveOffset=true` сохраняет оффсет из строки; `false` — приводит к `defaultTimezone`
- `strictMissingTimezone=true` — отсутствие оффсета считается ошибкой
- `invalidBehavior` управляет реакцией на ошибку:
  - `Throw` — исключение
  - `Null` — вернуть `null`

Важно: `invalidBehavior=Null` безопасен только для `?DateTimeInterface` (или при `DefaultValue`),
иначе на уровне конструктора DTO будет `TypeError`.

### Serialize
Для DTO DX serialization правила берутся из `DateTimeSerializationPolicy`:
- `format` — формат выходной строки
- `timezone` — если задана, дата приводится к этой зоне перед форматированием
- дефолтный path форматирует только `DateTimeInterface`
- строка не перепарсивается автоматически как дата при DTO serialization

Для request-level сериализации используется `ClientConfig::requestDateTime`.
Для outbound body по умолчанию используется transport-level wire policy.

### Приоритеты
1) `#[Cast(DateTimeCast::class, ...)]` на свойстве  
2) `DateTimeFrom` / `DateTimeTo` на свойстве  
3) DTO profile-level policy  
4) zero-config default

### Union типы и выбор ветки
Для union‑полей SDK сначала пытается выбрать тип, совпадающий с runtime‑значением.
Это убирает зависимость от порядка типов в объявлении:

- `DateTimeInterface|string` со строковым значением обрабатывается как строка
- `string|DateTimeInterface` с объектом даты обрабатывается как `DateTimeInterface`

Если ни одна ветка union не совпала по runtime, используется первый non-null тип
(поведение совместимо с прежним fallback по выбору ветки, но без скрытого string reparsing на serialize).

## Enum‑сериализация

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
См. [ClientConfig: Serialization](./client-config/serialization.md) — там показано разделение
body DTO profile и request-level enum policy.

## Где детали
- Атрибуты запросов: [Request Attributes](./attributes/request.md)
- NamingStrategy: [Naming Strategy](./naming-strategy.md)
- Casts: [Casts](./casts.md)
