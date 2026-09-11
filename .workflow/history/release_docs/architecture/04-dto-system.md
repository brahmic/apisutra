# DTO System — Гидрация и Касты

Система преобразования JSON → типизированные объекты.

---

## Компоненты

```
JSON response
    ↓
Hydrator
    ├── computed() — вычисляемые поля
    ├── #[From] — маппинг имён
    ├── CastRegistry — преобразование типов
    │   ├── Auto casts (по типу свойства)
    │   └── #[Cast] (явные)
    └── #[Nested] — вложенные DTO
    ↓
DTO instance
```

`Hydrator` и `Serializer` используют общий `AttributeMetadataCache`,
который предоставляет клиент через `AttributeMetadataCacheProviderInterface`.

---

## Hydrator

Основной сервис гидрации.

```php
class Hydrator
{
    private static ?self $default = null;
    
    public function __construct(
        private readonly CastRegistry $casts,
        private readonly AttributeRegistry $attributes,
    ) {}
    
    /**
     * Singleton для DTO::from() без явного context
     * Использует CastRegistry::global()
     */
    public static function default(): self
    {
        return self::$default ??= new self(
            CastRegistry::global(),
            new AttributeRegistry(),
        );
    }
    
    /**
     * Гидрировать данные в DTO
     * Context опциональный — для from() без context
     */
    public function hydrate(
        array|object $data,
        string $dtoClass,
        ?PipelineContext $context = null,
    ): object;
    
    /**
     * Гидрировать массив в коллекцию DTO
     */
    public function hydrateCollection(
        array $items,
        string $dtoClass,
        ?PipelineContext $context = null,
    ): array;
}
```

**Поведение с/без context:**

| Аспект | С context | Без context |
|--------|-----------|-------------|
| NamingStrategy | Из config | None (as-is) |
| Casts из config | ✅ | ✅ (через global registry) |
| `#[Cast]`, `#[Nested]`, `#[From]` | ✅ | ✅ |
| `computed()` | ✅ | ✅ (ctx = null) |

### Алгоритм гидрации

```
1. computed()
   - Если DTO implements ResponseDtoInterface
   - $data = DtoClass::computed($data, $context)

2. Для каждого свойства DTO:
   a. Определить source key:
      - #[From('api_name')] → 'api_name'
      - NamingStrategy → transform(propertyName)
      - default → propertyName
   
   b. Извлечь значение:
      - dot notation: 'nested.path' → $data['nested']['path']
      - simple: 'key' → $data['key']
   
   c. Определить cast:
      - #[Cast(SomeCast::class)] → явный
      - По типу свойства → auto cast
      - null → без преобразования
   
   d. Применить cast:
      - $value = $cast->hydrate($value, $context)
   
   e. Обработать #[Nested]:
      - array<Type> → hydrateCollection()
      - Type → hydrate()
      - Polymorphic → по discriminator

3. Создать DTO через конструктор
```

---

## CastRegistry

Реестр кастов.

```php
class CastRegistry
{
    private static ?self $global = null;
    
    /**
     * Глобальный registry — для Dto::from() без context
     */
    public static function global(): self
    {
        return self::$global ??= new self();
    }
    
    /**
     * Получить каст для типа
     */
    public function get(string $type): ?CastInterface;
    
    /**
     * Зарегистрировать каст
     */
    public function register(string $type, CastInterface|string $cast): void;
}
```

**Client регистрирует casts при создании (scoped):**

```php
// В AbstractClient::__construct()
$this->castRegistry = new CastRegistry();
foreach ($config->casts as $type => $cast) {
    $this->castRegistry->register($type, $cast);
}
```

Глобальный registry используется только для `Dto::from()` без context.
В pipeline применяется registry клиента, чтобы избежать конфликтов
между разными SDK‑клиентами в одном процессе.

Для pipeline `Hydrator` и `Serializer` создаются с `$this->castRegistry`
и общим `AttributeRegistry` клиента.

### Auto Casts (встроенные)

| Тип свойства | Каст |
|--------------|------|
| `Carbon` | `CarbonCast` |
| `DateTimeInterface` | `DateTimeCast` |
| `BackedEnum` | `EnumCast` |
| `UnitEnum` | `EnumCast` |

### Приоритет кастов

```
1. #[Cast] на свойстве (высший)
2. Глобальный каст из ClientConfig::casts
3. Auto cast по типу свойства
4. Без преобразования (lowest)
```

---

## CastInterface

```php
interface CastInterface
{
    /**
     * API → DTO (десериализация)
     * Context nullable для поддержки from() без context
     */
    public function hydrate(mixed $value, ?PipelineContext $context = null): mixed;
    
    /**
     * DTO → API (сериализация)
     * Context nullable для консистентности
     */
    public function serialize(mixed $value, ?PipelineContext $context = null): mixed;
}
```

### Пример каста

```php
readonly class MoneyCast implements CastInterface
{
    public function __construct(
        private string $currency = 'RUB',
    ) {}
    
    public function hydrate(mixed $value, ?PipelineContext $context = null): Money
    {
        return new Money((int) $value, $this->currency);
    }
    
    public function serialize(mixed $value, ?PipelineContext $context = null): int
    {
        return $value->getAmount();
    }
}
```

---

## Атрибуты гидрации

### #[From]

Маппинг имени поля.

```php
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class From
{
    public function __construct(
        public string $name,  // Имя в JSON или dot-path
    ) {}
}
```

**Использование:**

```php
readonly class UserDto extends AbstractDto
{
    #[From('user_name')]
    public string $name;
    
    #[From('meta.created_at')]
    public Carbon $createdAt;
}
```

### #[Cast]

Явный каст.

```php
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Cast
{
    public function __construct(
        public string $class,     // CastInterface implementation
        public mixed ...$args,    // Аргументы конструктора каста
    ) {}
}
```

**Использование:**

```php
readonly class OrderDto extends AbstractDto
{
    #[Cast(MoneyCast::class, currency: 'USD')]
    public Money $total;
    
    #[Cast(DateTimeCast::class, format: 'd.m.Y')]
    public Carbon $date;
}
```

### #[Nested]

Вложенные DTO и коллекции.

```php
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Nested
{
    public function __construct(
        public ?string $type = null,           // Тип элемента для array
        public ?string $from = null,           // Путь к данным в ответе
        public ?string $each = null,           // Путь внутри каждого элемента
        public ?string $discriminator = null,  // Поле для polymorphic
        public ?array $map = null,             // discriminator → class
    ) {}
}
```

**Использование:**

```php
readonly class OrderDto extends AbstractDto
{
    // Одиночный вложенный
    public CustomerDto $customer;  // Без атрибута, по типу
    
    // Массив DTO
    #[Nested(type: ItemDto::class)]
    public array $items;
    
    // Polymorphic
    #[Nested(
        discriminator: 'type',
        map: [
            'card' => CardPayment::class,
            'cash' => CashPayment::class,
        ],
    )]
    public PaymentInterface $payment;
}
```

---

## Обработка Nested

### Простой тип (без атрибута)

```php
public CustomerDto $customer;
```

```
1. Тип CustomerDto — не scalar
2. CustomerDto extends AbstractDto
3. Рекурсивно: hydrate($data['customer'], CustomerDto::class)
```

### Array с типом

```php
#[Nested(type: ItemDto::class)]
public array $items;
```

```
1. Атрибут указывает тип элемента
2. hydrateCollection($data['items'], ItemDto::class)
3. Результат: ItemDto[]
```

### Polymorphic

```php
#[Nested(discriminator: 'type', map: [...])]
public PaymentInterface $payment;
```

```
1. Прочитать $data['payment']['type']
2. Найти класс в map
3. hydrate($data['payment'], resolvedClass)
```

---

## computed()

Вычисляемые поля перед гидрацией.

```php
readonly class InvoiceDto extends AbstractResponseDto
{
    public function __construct(
        public string $id,
        public int $amount,
        public int $tax,
        public int $total,  // computed
    ) {}
    
    public static function computed(array $data, ?PipelineContext $context = null): array
    {
        $data['total'] = $data['amount'] + $data['tax'];
        return $data;
    }
}
```

**Когда использовать:**
- Поле отсутствует в API, но нужно в DTO
- Агрегация/вычисление из других полей
- Значения по умолчанию

---

## Сериализация (обратное направление)

При отправке Request с DTO-свойствами:

```php
#[Post('/orders')]
class CreateOrder extends AbstractRequest
{
    public function __construct(
        public OrderInput $order,  // DTO
    ) {}
}
```

Serializer вызывает `$cast->serialize()` в обратном порядке.

---

## Performance: AttributeMetadataCache

Reflection — дорогая операция. SDK кеширует результаты сканирования атрибутов.
AttributeRegistry использует AttributeMetadataCache, а Serializer/Hydrator
получают метаданные через registry (общий кеш для сериализации и гидрации).

```php
class AttributeMetadataCache
{
    private array $cache = [];
    
    public function get(string $class): ?ClassMetadata
    {
        return $this->cache[$class] ?? null;
    }
    
    public function set(string $class, ClassMetadata $meta): void
    {
        $this->cache[$class] = $meta;
    }
    
    public function warmup(array $classes): void
    {
        foreach ($classes as $class) {
            if (!isset($this->cache[$class])) {
                $this->cache[$class] = $this->scan($class);
            }
        }
    }
}
```

**ClassMetadata** содержит:
- HTTP method и path (из атрибута класса)
- Property mappings (Path, Query, Body, Header)
- Validation rules
- Cast mappings
- Hook bindings

**Поведение по Environment:**

| Environment | Cache | Описание |
|-------------|-------|----------|
| `Local` | Off | Каждый запрос — свежий scan (удобно при разработке) |
| `Testing` | Off | Тесты видят изменения сразу |
| `Staging` | On | Проверка production-like |
| `Production` | On | Максимальная производительность |

Environment берётся из `ClientConfig::environment`.

**Warmup в Laravel:**

```php
// В ServiceProvider::boot()
if (app()->environment('production')) {
    $cache = app(AttributeMetadataCache::class);
    $cache->warmup([
        GetUser::class,
        CreateOrder::class,
        // ...
    ]);
}
```

---

## Резюме

| Компонент | Назначение |
|-----------|------------|
| `Hydrator` | Оркестрация JSON → DTO |
| `CastRegistry` | Хранение и lookup кастов |
| `CastInterface` | Контракт преобразования |
| `#[From]` | Маппинг имени поля |
| `#[Cast]` | Явное указание каста |
| `#[Nested]` | Вложенные DTO, коллекции, polymorphic |
| `computed()` | Вычисляемые поля |
| `AttributeMetadataCache` | Кеш Reflection для производительности |
