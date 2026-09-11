# Casts

Касты применяются при сериализации запросов и при гидрации DTO.
Они помогают преобразовывать типы данных без ручной логики.

## Приоритет применения
1) `#[Cast]` на свойстве  
2) registry‑каст по типу, выбранному по runtime‑значению (для union)  
3) safe scalar auto-cast по declared type при гидрации (`int` / `float` / `bool` / `string`)  
4) встроенные правила по типу значения (DateTime/enum)

## Встроенные касты
- `BooleanCast`
- `IntegerCast`
- `FloatCast`
- `DateTimeCast`
- `EnumCast`
- `JsonCast`

## Boolean в текстовых полях

`BooleanCast` без аргументов сохраняет прежнее преобразование в PHP bool, включая
гидрацию. Необязательный формат меняет только его исходящую сериализацию:

```php
use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Casts\BooleanCast;
use Brahmic\ApiSutra\Enums\Serialization\BooleanFormat;

#[Query]
#[Cast(BooleanCast::class, BooleanFormat::Literal)]
public bool $enabled = false;
```

Получится `enabled=false` независимо от клиентского `textBooleanFormat`.
Numeric даёт строки `1`/`0`. С явным форматом serialize принимает bool или null;
другие значения вызывают `SerializationException`. Гидрация остаётся прежней.
Явный текстовый cast на JSON/DTO-поле также возвращает строку: применяйте его только
там, где этого требует API. Для общего правила query/multipart достаточно
[настройки клиента](client-config/serialization.md#текстовый-boolean).

## Регистрация кастов

```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Casts\DateTimeCast;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    casts: [
        DateTimeImmutable::class => DateTimeCast::class,
    ],
);
```

Если касты не регистрировать, работают только встроенные и атрибутные `#[Cast]`.
Регистрация нужна для провайдер‑специфичных типов или единых правил по типу.

## Атрибут #[Cast]
```php
use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;

#[Cast(DateTimeCast::class, format: DATE_ATOM)]
public DateTimeImmutable $createdAt;
```

## Примеры поведения
- **Safe scalar auto-cast**: для DTO hydration `"12"` может стать `12` для `int`, `"12.5"` -> `12.5` для `float`, `"true"` -> `true` для `bool`. Небезопасные преобразования не выполняются.
- **Enum**: для DX / `toArray()` формат задаётся через `DtoSerializationProfile`; для wire body — через `wireBodySerializationPolicy`; для query/header/path — через request-level config клиента.
- **DateTime**: типовой DX теперь идёт через `DtoHydrationProfile` / `DtoHydrate` / `DateTimeFrom` и `DtoSerializationProfile` / `DtoSerialize` / `DateTimeTo`. `#[Cast(DateTimeCast::class, ...)]` остаётся low-level escape hatch. Дефолтный `DateTimeCast::serialize()` принимает только `DateTimeInterface`.
- **Json**: `JsonCast` сериализует массив/объект в JSON‑строку.

## Где применяется
- **Request serialization** — свойства запроса
- **DTO hydration** — свойства DTO
