# Сериализация запросов

Маппинг свойств Request в HTTP-запрос: path, query, body, headers.

## Атрибуты маппинга

| Атрибут | Куда | Параметры |
|---------|------|-----------|
| `#[Path]` | URL path | `name` (опц.) |
| `#[Query]` | Query string | `name` (опц.), `arrayFormat`, `nullable` |
| `#[Body]` | Тело запроса | `nested` (опц.) |
| `#[Header]` | HTTP заголовок | `name` (обязателен) |
| `#[Ignore]` | Никуда | — |

---

## Convention (без атрибутов)

SDK автоматически определяет куда отправлять свойство:

| Условие | Куда |
|---------|------|
| Имя совпадает с `{placeholder}` в URL | Path |
| HTTP метод GET / DELETE | Query |
| HTTP метод POST / PUT / PATCH | Body |
| `private` / `protected` свойство | Игнорируется |

---

## Кейсы

### Простой GET

```php
#[Get('/orders')]
class ListOrders extends AbstractRequest
{
    public int $page = 1;      // → ?page=1
    public int $limit = 10;    // → ?limit=10
}
```

### Path параметры

```php
#[Get('/orders/{orderId}')]
class GetOrder extends AbstractRequest
{
    public string $orderId;    // → /orders/123 (auto из {orderId})
}

// Кастомное имя
#[Get('/orders/{id}')]
class GetOrder extends AbstractRequest
{
    #[Path('id')]
    public string $orderId;    // → /orders/123
}
```

### POST с query + body

```php
#[Post('/orders')]
class CreateOrder extends AbstractRequest
{
    #[Query]
    public bool $notify = false;   // → ?notify=false
    
    #[Query]
    public bool $async = false;    // → ?async=false
    
    public string $type;           // → body (convention)
    public array $items;           // → body (convention)
}
```

### Свойство в заголовок

```php
#[Post('/payments')]
class CreatePayment extends AbstractRequest
{
    #[Header('X-Idempotency-Key')]
    public string $idempotencyKey;
    
    public Money $amount;          // → body
}
```

### Исключение свойства

```php
#[Get('/orders')]
class ListOrders extends AbstractRequest
{
    public int $page = 1;
    
    #[Ignore]
    public string $cacheKey;       // служебное, не отправляется
}
```

Альтернатива — `private` свойство (не сериализуется по convention).

---

## Кастомные имена параметров

```php
#[Get('/orders')]
class ListOrders extends AbstractRequest
{
    #[Query('page_num')]
    public int $page;              // → ?page_num=1
    
    #[Query('per_page')]
    public int $limit;             // → ?per_page=10
}
```

---

## Вложенные объекты в body

DTO как свойство сериализуется рекурсивно:

```php
#[Post('/orders')]
class CreateOrder extends AbstractRequest
{
    public CustomerData $customer;
    public array $items;
}

// Результат:
// {
//   "customer": { "name": "...", "email": "..." },
//   "items": [...]
// }
```

Если объект реализует `JsonSerializable` или имеет метод `toArray()` — используется он.

---

## Массивы в query string

**Проблема:** разные API ожидают разный формат массивов.

| Формат | Query string |
|--------|-------------|
| `Brackets` | `?ids[]=1&ids[]=2` |
| `Indices` | `?ids[0]=1&ids[1]=2` |
| `Comma` | `?ids=1,2` |
| `Repeat` | `?ids=1&ids=2` |

### Конфигурация

**На уровне клиента (default):**

```php
new ClientConfig(
    queryArrayFormat: QueryArrayFormat::Brackets,
);
```

**На уровне свойства (переопределение):**

```php
#[Query(arrayFormat: QueryArrayFormat::Comma)]
public array $ids;
```

### QueryArrayFormat enum

```php
enum QueryArrayFormat: string
{
    case Brackets = 'brackets';  // ids[]=1&ids[]=2
    case Indices = 'indices';    // ids[0]=1&ids[1]=2
    case Comma = 'comma';        // ids=1,2
    case Repeat = 'repeat';      // ids=1&ids=2
}
```

---

## Null значения

**Проблема:** отправлять `null` или пропускать параметр?

### Конфигурация

**На уровне клиента (default):**

```php
new ClientConfig(
    serializeNulls: false,  // пропускать null (default)
);
```

**На уровне свойства (переопределение):**

```php
#[Query(nullable: true)]
public ?string $filter = null;  // отправит ?filter= даже если null
```

### Поведение

| `serializeNulls` | Значение | Query | Body |
|------------------|----------|-------|------|
| `false` | `null` | пропускается | пропускается |
| `true` | `null` | `?param=` | `"param": null` |

---

## Вложенная структура в body

Когда API ожидает вложенный JSON из плоских свойств:

**API ожидает:**
```json
{
  "PeopleQuery": {
    "LastName": "Иванов",
    "FirstName": "Иван"
  },
  "regions": ["77"]
}
```

**Запрос:**
```php
#[Post('/check')]
class CheckPerson extends AbstractRequest
{
    #[Body(nested: 'PeopleQuery.LastName')]
    public string $lastName;
    
    #[Body(nested: 'PeopleQuery.FirstName')]
    public string $firstName;
    
    public array $regions;  // верхний уровень (convention)
}
```

SDK группирует поля с общим префиксом в nested структуру.

---

## Валидация — #[Validate]

Валидация свойств перед отправкой запроса. Синтаксис Laravel rules.

### Использование

```php
#[Post('/people-check')]
class GetPersonUuid extends AbstractRequest
{
    #[Validate('required|array|max:2')]
    public array $regions;
    
    #[Validate('required|regex:/^[а-яёА-ЯЁ\- ]+/')]
    public string $lastName;
    
    #[Validate('required|regex:/^[а-яёА-ЯЁ\- ]+/')]
    public string $firstName;
    
    #[Validate('nullable|regex:/^[а-яёА-ЯЁ\- ]+/')]
    public ?string $patronymic = null;
    
    #[Validate('nullable|date_format:d.m.Y')]
    public ?Carbon $birthDate = null;
    
    #[Validate('nullable|digits:4')]
    public ?string $passportSerial = null;
    
    #[Validate('nullable|digits:6')]
    public ?string $passportNumber = null;
    
    #[Validate('nullable|digits_between:10,12')]
    public ?string $inn = null;
}
```

### Механизм

1. SDK сканирует `#[Validate]` на свойствах
2. Собирает массив правил: `['lastName' => 'required|regex:...', ...]`
3. Вызывает `Validator::make($data, $rules)`
4. При ошибке — не отправляет запрос, возвращает `ValidationError`

### Nullable = Optional

Свойства с `?` типом и `nullable` в правилах — необязательные. Если `null` — не отправляются (при `serializeNulls: false`).

### Кастомные сообщения

Через параметр атрибута `#[Validate('rules', message: '...')]` или метод `validationMessages()` в классе запроса.

---

## Связь с ClientConfig

```php
new ClientConfig(
    // ... базовые настройки ...
    
    // Сериализация запросов
    queryArrayFormat: QueryArrayFormat::Brackets,
    serializeNulls: false,
    namingStrategy: NamingStrategy::None,
);
```

Настройки клиента — defaults. Атрибуты на свойствах — переопределение.

См. [NamingStrategy](./naming-strategy.md)

---

## URL Resolution

### Приоритет endpoint

```
resolveEndpoint() метод → #[Get('/path')] атрибут → ошибка
```

### Приоритет baseUrl

```
withBaseUrl() runtime → resolveBaseUrl() метод → ClientConfig::baseUrl
```

### Атрибут (основной способ)

```php
#[Get('/users/{userId}')]
class GetUser extends AbstractRequest
{
    public function __construct(public string $userId) {}
}
```

### resolveEndpoint() — динамический путь

```php
#[Get]  // без пути в атрибуте
class GetReport extends AbstractRequest
{
    public function __construct(
        public string $type,
        public string $id,
    ) {}
    
    protected function resolveEndpoint(): string
    {
        return "/reports/{$this->type}/{$this->id}";
    }
}
```

### resolveBaseUrl() — другой домен

```php
#[Post('/upload')]
class UploadFile extends AbstractRequest
{
    protected function resolveBaseUrl(): string
    {
        return 'https://cdn.example.com';
    }
}
```

### withBaseUrl() — runtime переопределение

```php
$request->withBaseUrl('https://staging.example.com')->send();
```

---

## Резюме

| Задача | Решение |
|--------|---------|
| Явно в query | `#[Query]` |
| Явно в body | `#[Body]` |
| Явно в path | `#[Path]` |
| В заголовок | `#[Header('X-Name')]` |
| Не отправлять | `#[Ignore]` или `private` |
| Кастомное имя | `#[Query('custom_name')]` |
| Формат массива | `#[Query(arrayFormat: ...)]` |
| Отправлять null | `#[Query(nullable: true)]` |
| Вложенная структура | `#[Body(nested: 'Path.To')]` |
| Валидация | `#[Validate('rules')]` |
| Динамический endpoint | `resolveEndpoint()` |
| Другой baseUrl | `resolveBaseUrl()` или `withBaseUrl()` |

---

## Связанные документы

- [Валидация](./validation.md) — подробно о `#[Validate]` и кастомных сообщениях
- [NamingStrategy](./naming-strategy.md) — автоматическое преобразование имён
