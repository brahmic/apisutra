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

### Диапазон IntegerCast и вложенный JSON

`IntegerCast` проверяет переполнение до преобразования. При hydrate число вне
диапазона PHP int даёт `HydrationException`; при serialize — `SerializationException`
(в запросе `serialization_error` до HTTP). Null остаётся null, обычные преобразования
сохраняются. Целочисленные строки сравниваются без промежуточного float, включая
ведущие нули. Float и дробные/экспоненциальные строки проверяются в представлении
float; около границы диапазона округление может привести к отказу. Для точного
целого передавайте целочисленную строку или int, для большого ID — сохраняйте string.

### JsonCast

`JsonCast::hydrate()` строго разбирает строку как JSON: malformed JSON, пустая строка,
whitespace, invalid UTF-8 и превышение глубины 512 дают `HydrationException` с reason
`invalid_json` и исходным `JsonException` в цепочке `previous`. В DTO добавляется путь
поля, в запросе получается `hydration_error`. Nullable-поле также не скрывает
повреждённую JSON-строку за null. Новая настройка клиента не требуется.

JSON `null`, `false`, `0`, строка, массив и объект сохраняют прежние результаты;
объект декодируется в ассоциативный массив. PHP null и нестроковые значения проходят
без JSON-декодирования. Результат затем проверяется по типу поля: например, JSON
`false` корректен, но для `array`-поля даст `invalid_field_type`.
Большие целочисленные JSON-литералы сохраняются строками. Serialize остаётся строгим.
Общая политика чисел — в [сериализации](serialization.md#большие-целые-в-ответах).

Миграция: код, ожидавший null от повреждённого JSON, теперь должен обрабатывать ошибку
или задавать собственное преобразование по контракту провайдера. Пользовательские
casts сохраняют приоритет и отвечают за точность своего преобразования.

### Даты и enum

Непринятая дата в режиме `DateTimeInvalidBehavior::Throw` даёт `HydrationException`
с reason `invalid_datetime`; expected содержит объявленный формат. Исходная строка
не включается в message. Режим `Null` сохраняет null; если поле его не допускает,
дальнейшая проверка даст `null_not_allowed`. Неверная timezone остаётся ошибкой
конфигурации. То же разделение применяется к явному `DateTimeCast`.

`EnumCast` сохраняет null для неизвестного backed enum value. Неподходящий тип входа
даёт `invalid_field_type`; использование non-backed enum для hydrate считается
ошибкой конфигурации. [Общие правила полей DTO](dto.md#обязательные-поля-и-ошибки-гидратации).

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
