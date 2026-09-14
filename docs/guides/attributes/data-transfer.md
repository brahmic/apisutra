# Data Transfer attributes

Атрибуты для маппинга и валидации DTO. Применяются к свойствам DTO.

## Когда использовать
- **Map** — когда один и тот же внешний ключ нужен и для hydrate, и для serialize.
- **From** — когда входное поле в ответе API называется иначе или лежит в глубине.
- **To** — когда ключ при отправке должен отличаться от имени свойства DTO.
- **Cast** — когда тип нужно преобразовать (даты, enum, числа).
- **About** — когда нужно описать бизнес-смысл response DTO поля для документации
  и tooling.
- **DateTimeFrom / DateTimeTo** — когда для даты нужен типовой property-level override без low-level `Cast`.
- **EmptyStringAsNull** — когда пустая строка в ответе провайдера должна трактоваться как `null`.
- **Nested** — когда поле содержит вложенный объект или список объектов.
- **DefaultValue** — когда нужен дефолт или проверка найденного значения через provider.
- **Validate/Label** — когда нужно локально валидировать DTO и получить читаемые ошибки.

## Cast
**Параметры:**  
- `class: class-string<CastInterface>`  
- `...args` — аргументы конструктора  

Пример:
```php
#[Cast(DateTimeCast::class)]
public DateTimeImmutable $createdAt;
```

`#[Cast]` имеет приоритет над built-in auto-cast.
Для обычных scalar cases (`int` / `float` / `bool` / `string`) явный `Cast`
часто больше не нужен, если провайдер отдаёт безопасно приводимое значение.

## About
`#[About]` описывает бизнес-смысл DTO-поля для документации, анализа и export tooling.

Минимальный вариант:
```php
#[About(title: 'ИНН физического лица')]
public ?string $inn = null;
```

Расширенный вариант:
```php
#[About(
    title: 'Данные паспорта',
    description: 'Структурированные паспортные данные, если провайдер вернул их отдельным объектом.',
    example: [
        'series' => '1234',
        'number' => '567890',
        'issued_at' => '2020-01-15',
    ],
    format: 'object',
)]
public ?array $passport = null;
```

Поля:
- `title` — обязательное короткое человекочитаемое имя поля
- `description` — развёрнутое бизнес-описание поля
- `example` — один типовой пример значения; может быть scalar, JSON-строка или array
- `examples` — несколько примеров, каждый подчиняется тем же правилам, что `example`
- `format` — человекочитаемая подсказка о формате, если PHP-типа недостаточно
- `nullableReason` — причина, почему поле может быть `null`
- `note` — дополнительная оговорка или важный нюанс

`About` не дублирует техническую схему поля. Тип, nullable, enum, nested DTO,
collection shape, external field name и casts должны извлекаться из PHP-типа и
атрибутов `From`, `Map`, `Nested`, `Cast` и т.п.

Общее правило заполнения: optional-поля нельзя заполнять догадками. Значения должны
опираться на явный контракт провайдера, документацию, пользовательское описание или
проверенный анализ. Если данных недостаточно, поле остаётся `null`. Это особенно
важно для AI-assisted разметки DTO.

Для `nullableReason` правило строгое: заполняйте его только если причина явно
известна. Если причина неизвестна или есть только предположение, оставляйте `null`.

Для JSON-примеров допустимы оба варианта:
- `array` — структурированный пример; exporter может отрендерить его как JSON
- `string` — буквальный пример значения; он не должен автоматически парситься как JSON

## DateTimeFrom / DateTimeTo
Используйте их для типового date-time DX:

```php
#[DateTimeFrom(format: DATE_ATOM, defaultTimezone: 'UTC')]
#[DateTimeTo(format: DATE_ATOM, timezone: 'UTC')]
public DateTimeImmutable $createdAt;
```

- `DateTimeFrom` влияет только на hydration
- `DateTimeTo` влияет только на DTO body serialization
- `#[Cast]` по-прежнему сильнее этих атрибутов и остаётся escape hatch

## EmptyStringAsNull
Точечная hydration-нормализация:

```php
#[EmptyStringAsNull]
public ?string $middleName = null;
```

Опция:
- `blank: true` — считать `null` не только `''`, но и строки из пробелов

Подробное поведение и рекомендации: [DTO guide](../dto.md).

## Map
**Параметры:**  
- `name: string` — имя внешнего ключа

Пример:
```php
#[Map('user_id')]
public int $userId;
```

`Map` работает в обе стороны:
- hydrate из `user_id`
- serialize в `user_id`

Приоритеты:
- hydrate: `From` -> `Map` -> `NamingStrategy`
- serialize: `To` -> `Map` -> `NamingStrategy`

## From
**Параметры:**  
- `name: string` — путь/ключ в ответе  
- `fallback: array = []` — альтернативные ключи  

Пример:
```php
#[From('data.id', fallback: ['id'])]
public int $id;
```

## To
**Параметры:**  
- `name: string` — имя ключа при сериализации  

## Что когда использовать
| Сценарий | Рекомендация |
|---|---|
| Один и тот же ключ в обе стороны | `Map` |
| Только hydrate, нужен dot-path/fallback | `From` |
| Только serialize | `To` |
| Разные ключи на вход и выход | `From` + `To` |
| Базовое правило для всего DTO | `NamingStrategy` |

## Nested
**Параметры:**  
- `type?: string` — конкретный класс одиночного DTO или элемента коллекции
- `itemCast?: string` — cast для каждого элемента массива перед дальнейшей hydration/type-обработкой  
- `from?: string` — путь в ответе  
- `fallback: array = []`  
- `each?: string` — для коллекций  
- `discriminator?: string` — путь к discriminator (для `Value`), либо путь к объекту-обертке (для `Key`)  
- `map?: ?array` — маппинг discriminator -> class-string DTO
- `discriminatorMode: NestedDiscriminatorMode = Value` — откуда брать discriminator (`Value` | `Key`)
- `unknownVariant: NestedUnknownVariant = KeepRaw` — политика для неизвестных вариантов (`KeepRaw` | `Skip` | `Error`)

`itemCast` нужен, когда nested-массив уже структурно корректный, но каждый элемент надо дополнительно преобразовать.
Типовой пример: `list<data-uri-string> -> list<Base64File>`.
Класс `itemCast` должен реализовывать `CastInterface` и создаваться без аргументов.

### Одиночный объект и коллекция

Гидратор выбирает назначение по объявлению свойства, до разбора его данных:

| Объявление | Обработка |
| --- | --- |
| `#[Nested] AddressDto` или `?AddressDto` | Одиночный DTO указанного класса |
| `#[Nested(type: AddressDto::class)] AddressDto` | Одиночный DTO; допустим также конкретный подтип |
| Интерфейс или абстрактный класс свойства | Для одиночного объекта нужен совместимый конкретный `Nested.type` |
| `object` | Для одиночного объекта нужен конкретный `Nested.type` |
| `array`, `iterable`, `mixed`, отсутствие native-типа | Сохраняется обработка массива элементов; `type` задаёт класс элемента |
| Класс `Traversable`, включая `AbstractCollection` / `AbstractTypedCollection` | Коллекция; `type` задаёт класс элемента, `map` — классы вариантов |
| Пользовательская обёртка и отдельный, несовместимый с ней `type` элемента | Коллекция, если есть вызываемая `fromArray()` либо публичный конструктор, принимающий массив первым аргументом без других обязательных аргументов |
| Фабричная обёртка с `map` без `type` | Коллекция через `fromArray()` |
| Union только классов/интерфейсов | Для одиночного объекта обязателен `type`, совместимый хотя бы с одной веткой |

`null` не выбирает ветку union. Union с разной кардинальностью, например
`AddressDto|array`, union без необходимого `type`, несовместимый класс, недоступный
конструктор или intersection дают `ConfigurationException`. Параметры списка
`each`, `itemCast`, `discriminator`, `map` несовместимы с одиночным объектом.
Эта проверка выполняется при обработке ненулевого найденного значения.

Одиночный DTO принимает ассоциативный массив или PHP-объект. Гидратация проходит
через обычные проверки полей и один вызов конструктора, включая готовый PHP DTO.
Scalar и непустой PHP list дают `unexpected_response_shape` по пути свойства.
Пустой массив проходит проверку полей дочернего DTO: например, отсутствие `city`
даёт `required_field_missing` с путём `address.city`.
После JSON decode с `assoc=true` пустые `{}` и `[]` неразличимы; `Nested` это
различие не восстанавливает. [Исполняемый пример](../dto.md#вложенные-dto).

`Nested.from` переопределяет путь `From` / `Map` / имени свойства.
Непустой `Nested.fallback` имеет приоритет над `From.fallback`; fallback применяется
только при отсутствии ключа, а найденный null сохраняется. `DefaultValue`,
constructor default, nullable и пустая typed collection сохраняют
[общие правила missing/null](../dto.md#обязательные-поля-и-ошибки-гидратации).

Для списков порядок остаётся `each` → `itemCast` → гидратация элементов или
discriminator → обёртка коллекции. `Nested` не включает проверку PHPDoc `list<T>`;
форма списка и scalar-элементы требуют явных проверок. Ошибка элемента содержит
его порядковый индекс. `#[Cast]` всего свойства при наличии `Nested` не выполняется.

Миграция: корректный одиночный объект из JSON теперь гидратируется, ошибочный
получает путь дочернего поля без фиктивного `[0]` и вызова DTO как обёртки списка.
Для неоднозначных деклараций задайте конкретный класс и определите в native-типе,
содержит свойство объект или коллекцию.

## DefaultValue
**Параметры:**  
- `value?: int|float|string|bool|array|null`  
- `provider?: string` — класс `DefaultValueProviderInterface`, создаваемый без аргументов
- `when: array = [ValueState::Missing]`  

Если `provider` не задан — используется `value`.
Провайдер нужен, когда значение зависит от контекста или требует логики.
Ненулевой `value` вместе с `provider` — ошибка конфигурации.

### Provider для найденного значения

Provider может вычислить замену, вернуть проверенное значение или отклонить его.
`when: [ValueState::Present]` запускает его для найденного ненулевого значения
перед `Nested` и casts. `DefaultValue` не повторяемый атрибут: для запрета null
и проверки формы используйте один provider с `when: [ValueState::Null, ValueState::Present]`.

```php
<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Attributes\DataTransfer\DefaultValue;
use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use Brahmic\ApiSutra\Enums\DataTransfer\ValueState;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class ListShapeProvider implements DefaultValueProviderInterface
{
    /** @param array<string, mixed> $source */
    public function resolve(mixed $value, ValueState $state, array $source, ?PipelineContext $context): mixed
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw HydrationException::invalidValue('invalid_list_shape', 'list', get_debug_type($value));
        }

        return $value;
    }
}

final readonly class KnownRecordDto
{
    public function __construct(public string $city)
    {
    }
}

final readonly class RecordsDto
{
    /** @param list<mixed>|null $items */
    public function __construct(
        #[DefaultValue(provider: ListShapeProvider::class, when: [ValueState::Null, ValueState::Present])]
        #[Nested(discriminator: 'kind', map: ['known' => KnownRecordDto::class])]
        public ?array $items = null,
    ) {
    }
}

$dto = Hydrator::default()->hydrate([
    'items' => [['kind' => 'known', 'city' => 'Sample'], ['kind' => 'future', 'enabled' => false]],
], RecordsDto::class);
```

Missing сохраняет constructor default null, пустой list принимается.
Явный null, ассоциативный массив и разреженные индексы отклоняются с путём `items`.
После проверки формы `Nested` создаёт известный DTO и сохраняет неизвестный вариант
по умолчанию `KeepRaw`. Неверный тип `city` у известного элемента даёт `items[0].city`.

Порядок обработки состояния:

1. Поиск значения и fallback.
2. Нормализация пустой строки, если включена политикой или `EmptyStringAsNull`.
3. Применимый `DefaultValue` с текущими `$value` и `$state`; `$source` содержит данные текущего DTO.
4. Встроенный fallback missing typed collection, затем `Nested` или casts и проверка типа.

По умолчанию действует `Keep`: provider видит исходную пустую строку и `Present`.
При преобразовании пустой строки в null он видит `Null`.
Если на свойстве есть `#[Cast]`, нормализация пустой строки пропускается даже
при `EmptyStringAsNull`; provider по-прежнему выполняется до cast.

Для отказа используйте структурированную `HydrationException::invalidValue()`:
гидратор добавит имя текущего поля, родителей, индексы и `Returns.unwrap`.
Provider задаёт только локальный суффикс пути, если он нужен; `reason`, `expected`,
`actual` и цепочка `previous` сохраняются. Не включайте значения ответа в текст ошибки.
`ConfigurationException` остаётся ошибкой конфигурации. У прежних исключений
без `reason` автоматическое дополнение пути не выполняется.

Миграция диагностики: ошибка provider поля `count` внутри `child` теперь имеет
путь `child.count`, после unwrap `data` — `data.child.count`. Обработчики,
сопоставлявшие прежний неполный путь, нужно обновить.

### Typed collections: built-in fallback
Для **non-nullable typed collection** (`AbstractTypedCollection`) гидратор
автоматически использует пустую коллекцию при `ValueState::Missing`,
даже без `DefaultValue`.

Порядок:
1. `DefaultValue` (если он покрывает текущее состояние)
2. built-in fallback для non-nullable typed collection при `Missing`
3. обычное поведение гидрации

Важно:
- fallback не применяется к `ValueState::Null`
- fallback не применяется к nullable-collection (`?MyCollection`)
- если нужен `null -> []`, используйте явный `DefaultValue`
- `DefaultValue(... when: [ValueState::Null])` не отключает built-in fallback для `Missing`

## Label
**Параметры:**  
- `name: string` — человекочитаемое имя поля  

## Validate
**Параметры:**  
- `rules: string` — правила валидации  
- `message?: string` — кастомное сообщение  

Если `message` не задан — используется дефолтное сообщение валидатора.

Пример:
```php
#[Label('Email')]
#[Validate('required|email', message: 'Некорректный email')]
public string $email;
```
