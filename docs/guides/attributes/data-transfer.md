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
- **DefaultValue** — когда значение может отсутствовать/быть null и нужен дефолт.
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
- `type?: string` — класс DTO  
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

## DefaultValue
**Параметры:**  
- `value?: int|float|string|bool|array|null`  
- `provider?: string` — внешний провайдер значения  
- `when: array = [ValueState::Missing]`  

Если `provider` не задан — используется `value`.
Провайдер нужен, когда значение зависит от контекста или требует логики.

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
