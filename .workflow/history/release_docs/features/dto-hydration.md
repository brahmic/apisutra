# DTO и Гидрация

## Иерархия DTO

### Интерфейсы

```php
interface DtoInterface
{
    public static function from(array|object $data): static;
}

interface ResponseDtoInterface extends DtoInterface
{
    public static function computed(array $data, ?PipelineContext $ctx = null): array;
}

interface ValidatableInterface
{
    public function validate(): static;
    public function isValid(): bool;
    public function errors(): array;
}
```

### Абстракции

```php
// Базовый DTO с валидацией
abstract readonly class AbstractDto implements DtoInterface, ValidatableInterface
{
    use ValidatesAttributes;
    
    public static function from(array|object $data): static
    {
        return Hydrator::default()->hydrate($data, static::class);
    }
}

// Response DTO (корневой ответ от API)
abstract readonly class AbstractResponseDto extends AbstractDto implements ResponseDtoInterface
{
    public static function computed(array $data, ?PipelineContext $ctx = null): array
    {
        return $data; // default: без изменений
    }
}
```

### Создание DTO

```php
// Из массива
$user = UserDto::from(['name' => 'John', 'email' => 'john@example.com']);

// Из Laravel Model (или любого объекта с toArray())
$user = UserDto::from($userModel);

// С валидацией
$user = UserDto::from($data)->validate();  // throws ValidationException

// Проверка без exception
if ($dto->isValid()) {
    // ...
}
```

### Использование

```php
// Корневой Response
readonly class OrderResponse extends AbstractResponseDto
{
    public function __construct(
        public string $id,
        public CustomerDto $customer,
        #[Nested(type: ItemDto::class)]
        public array $items,
    ) {}
}

// Вложенные DTO
readonly class CustomerDto extends AbstractDto
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}
}
```

---

## Атрибуты

### На Request — #[Returns]

Указывает корневой DTO ответа:

```php
#[Get('/orders/{id}')]
#[Returns(OrderResponse::class)]
class GetOrder extends AbstractRequest {}
```

С параметрами (unwrap обёртки):

```php
#[Returns(ApiWrapper::class, unwrap: 'data', type: OrderDto::class)]
class GetOrder extends AbstractRequest {}
```

### На DTO — #[Nested]

Один атрибут с параметрами для всех случаев гидрации свойств.

**Параметры:**

| Параметр | Назначение | Поддержка dot notation |
|----------|-----------|----------------------|
| `type` | Тип элемента (для массивов/коллекций) | — |
| `from` | Путь к данным в ответе | ✅ |
| `each` | Путь внутри каждого элемента | ✅ |
| `discriminator` | Поле для определения типа (полиморфизм) | ✅ |
| `map` | Маппинг значение → класс | — |

---

## Кейсы

### 1. Вложенный объект (автоматика)

Атрибут не нужен — SDK определяет по типу свойства:

```php
readonly class OrderResponse extends AbstractResponseDto
{
    public function __construct(
        public CustomerDto $customer,   // автогидрация
        public ?AddressDto $address,    // nullable — может быть null
    ) {}
}
```

### 2. Массив объектов

PHP не знает тип элементов — нужен `type`:

```php
readonly class OrderResponse extends AbstractResponseDto
{
    public function __construct(
        #[Nested(type: ItemDto::class)]
        public array $items,
    ) {}
}
```

### 3. Коллекция объектов

Тип свойства = Collection класс. SDK создаёт через конструктор:

```php
readonly class OrderResponse extends AbstractResponseDto
{
    public function __construct(
        #[Nested(type: ItemDto::class)]
        public ItemCollection $items,
    ) {}
}
```

SDK делает: `new ItemCollection([ItemDto, ItemDto, ...])`

### 4. Извлечение из вложенного ключа

```json
{ "response": { "data": { "user": { "name": "John" } } } }
```

```php
#[Nested(from: 'response.data.user')]
public UserDto $user;
```

### 5. Извлечение части из каждого элемента

```json
{
  "items": [
    { "meta": {...}, "payload": { "id": 1 } },
    { "meta": {...}, "payload": { "id": 2 } }
  ]
}
```

```php
#[Nested(type: ItemDto::class, each: 'payload')]
public array $items;
```

SDK берёт `payload` из каждого элемента.

### 6. Полиморфизм

Тип DTO зависит от значения поля:

```json
{
  "events": [
    { "type": "order", "orderId": "123" },
    { "type": "payment", "paymentId": "456" }
  ]
}
```

```php
#[Nested(discriminator: 'type', map: [
    'order' => OrderEventDto::class,
    'payment' => PaymentEventDto::class,
])]
public array $events;
```

### 7. Комбинация параметров

Всё вместе:

```json
{
  "response": {
    "events": [
      { "meta": { "kind": "order" }, "data": { "orderId": "123" } },
      { "meta": { "kind": "payment" }, "data": { "paymentId": "456" } }
    ]
  }
}
```

```php
#[Nested(
    from: 'response.events',
    each: 'data',
    discriminator: 'meta.kind',
    map: [
        'order' => OrderEventDto::class,
        'payment' => PaymentEventDto::class,
    ]
)]
public EventCollection $events;
```

---

## Переименование полей — #[From]

Когда имя поля в API отличается от имени свойства в PHP:

```php
readonly class DataDto extends AbstractDto
{
    #[From('court_name_val')]
    public ?string $courtNameVal;
    
    #[From('reg_date')]
    public ?Carbon $registeredAt;
    
    #[From('pledgers.orgs')]  // извлечение из вложенного пути
    public ?array $pledgers;
}
```

**Отличие от NamingStrategy:**
- `NamingStrategy` — глобальное правило для всех полей
- `#[From]` — переопределение для конкретного поля

**Приоритет:** `#[From]` всегда перекрывает `NamingStrategy`.

См. [NamingStrategy](./naming-strategy.md)

---

## Преобразование типов — #[Cast]

Для кастомного преобразования значений при гидрации:

```php
readonly class DataDto extends AbstractDto
{
    #[Cast(DateTimeCast::class)]
    public ?Carbon $caseDate;
    
    #[Cast(CourtParticipantRoleCast::class)]
    public ?CourtParticipantRole $role;
}
```

### CastInterface

```php
interface CastInterface
{
    // JSON → PHP (при гидрации)
    public function hydrate(mixed $value, ?PipelineContext $ctx = null): mixed;
    
    // PHP → JSON (при сериализации)
    public function serialize(mixed $value, ?PipelineContext $ctx = null): mixed;
}
```

### Пример Cast

```php
class DateTimeCast implements CastInterface
{
    public function __construct(
        private string $format = 'd.m.Y',
    ) {}
    
    public function hydrate(mixed $value, PipelineContext $ctx): ?Carbon
    {
        return $value ? Carbon::createFromFormat($this->format, $value) : null;
    }
    
    public function serialize(mixed $value, PipelineContext $ctx): ?string
    {
        return $value?->format($this->format);
    }
}
```

### Cast с параметрами

```php
#[Cast(DateTimeCast::class, format: 'd.m.Y H:i:s')]
public ?Carbon $createdAt;
```

---

## Вычисляемые свойства — computed()

Свойства, которые вычисляются на основе данных API, но не приходят напрямую.

### Проблема

DTO — readonly. Нельзя присвоить значение после создания объекта.

### Решение

Статический метод `computed()` вызывается **до** создания объекта:

```php
readonly class CaseDto extends AbstractResponseDto
{
    public ?string $caseId;
    public ?string $url;  // вычисляемое
    
    public static function computed(array $data, ?PipelineContext $ctx = null): array
    {
        return [
            'url' => isset($data['caseId']) 
                ? 'https://kad.arbitr.ru/Card/' . $data['caseId'] 
                : null,
        ];
    }
}
```

**Сценарий без контекста:** при `Dto::from()` `$ctx` будет `null`.
В этом случае в `computed()` нельзя опираться на pipeline‑данные
(`request`, `response`, `traceId`) — используйте только входной `data`.

**Механизм:**
1. SDK получает данные от API
2. Вызывает `DTO::computed($data, $ctx)` если метод существует
3. Мержит результат с данными
4. Создаёт readonly объект с полным набором полей

### Доступ к контексту

`PipelineContext` предоставляет:
- Конфигурацию клиента
- Информацию о запросе
- Роль в pipeline

---

## Convention over Configuration

| Ситуация | Атрибут нужен? |
|----------|---------------|
| Свойство с типом DTO | Нет — автогидрация |
| Свойство `Carbon`/`DateTime` | Нет — автокаст (ISO 8601) |
| Свойство `BackedEnum` | Нет — автокаст |
| Свойство `array` | Да — `#[Nested(type: ...)]` |
| Свойство Collection | Да — `#[Nested(type: ...)]` |
| Данные по другому пути | Да — `from` или `#[From]` |
| Полиморфизм | Да — `discriminator` + `map` |
| Кастомное преобразование | Да — `#[Cast]` |
| Вычисляемое свойство | Метод `computed()` |

---

## Связанные документы

- [Касты (Type Casting)](./casts.md) — подробно о `#[Cast]` и `CastInterface`
- [NamingStrategy](./naming-strategy.md) — автоматическое преобразование имён
