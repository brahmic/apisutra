# Attributes — Система атрибутов

Все атрибуты SDK и их обработка.

---

## HTTP Method

Определяют тип запроса и endpoint.

| Атрибут | Target | Описание |
|---------|--------|----------|
| `#[Get('/path')]` | CLASS | GET запрос |
| `#[Post('/path')]` | CLASS | POST запрос |
| `#[Put('/path')]` | CLASS | PUT запрос |
| `#[Patch('/path')]` | CLASS | PATCH запрос |
| `#[Delete('/path')]` | CLASS | DELETE запрос |

```php
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Get
{
    public function __construct(
        public string $path,
    ) {}
}
```

**Обработка:** `MethodAttributeHandler` — извлекает method и path.

---

## Request Mapping

Маппинг свойств в HTTP компоненты.

| Атрибут | Target | Описание |
|---------|--------|----------|
| `#[Path]` | PROPERTY | В URL path |
| `#[Query]` | PROPERTY | В query string |
| `#[Body]` | PROPERTY | В request body |
| `#[Header]` | PROPERTY | В HTTP header |
| `#[Ignore]` | PROPERTY | Исключить из сериализации |

```php
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Body
{
    public function __construct(
        public ?string $nested = null,  // Вложенный путь: 'data.user'
    ) {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Query
{
    public function __construct(
        public ?string $name = null,                    // Переопределить имя
        public ?QueryArrayFormat $arrayFormat = null,   // Формат массивов
        public ?bool $nullable = null,                  // Писать null в query
    ) {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Header
{
    public function __construct(
        public string $name,            // Имя header
    ) {}
}
```

**Обработка:** `Serializer` — строит `PreparedRequest`.

---

## Response

| Атрибут | Target | Описание |
|---------|--------|----------|
| `#[Returns(Dto::class)]` | CLASS | Тип response DTO |

```php
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Returns
{
    public function __construct(
        public string $type,
    ) {}
}
```

**Обработка:** `Pipeline` — определяет класс для гидрации.

---

## DTO Hydration

| Атрибут | Target | Описание |
|---------|--------|----------|
| `#[From('name')]` | PROPERTY | Маппинг имени из JSON |
| `#[Cast(Cast::class)]` | PROPERTY | Явный каст типа |
| `#[Nested]` | PROPERTY | Вложенный DTO / коллекция |

```php
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class From
{
    public function __construct(
        public string $name,            // Имя или dot-path
    ) {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Cast
{
    public function __construct(
        public string $class,
        public mixed ...$args,
    ) {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Nested
{
    public function __construct(
        public ?string $type = null,
        public ?string $from = null,
        public ?string $each = null,
        public ?string $discriminator = null,
        public ?array $map = null,
    ) {}
}
```

**Обработка:** `Hydrator` — десериализация JSON → DTO.

---

## Validation

| Атрибут | Target | Описание |
|---------|--------|----------|
| `#[Validate('rules')]` | PROPERTY | Правила валидации |
| `#[Label('Название')]` | PROPERTY | Человекочитаемое имя |

```php
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Validate
{
    public function __construct(
        public string $rules,
    ) {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Label
{
    public function __construct(
        public string $name,
    ) {}
}
```

**Обработка:** `Validator` — проверка перед отправкой.

---

## Cache

| Атрибут | Target | Описание |
|---------|--------|----------|
| `#[Cache(ttl: 300)]` | CLASS | Включить кеширование |

```php
use Brahmic\ApiSutra\Enums\Cache\CacheMode;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Cache
{
    public function __construct(
        public ?int $ttl = null,
        public CacheMode $mode = CacheMode::Enabled,
        public ?string $key = null,     // Кастомный ключ
    ) {}
}
```

**Обработка:** `Pipeline` — проверка/сохранение в кеш.

---

## Auth

| Атрибут | Target | Описание |
|---------|--------|----------|
| `#[NoAuth]` | CLASS | Без аутентификации |

```php
#[Attribute(Attribute::TARGET_CLASS)]
readonly class NoAuth {}
```

**Обработка:** `Pipeline` — пропуск authenticator.

---

## Retry

| Атрибут | Target | Описание |
|---------|--------|----------|
| `#[Retry]` | CLASS | Настройки retry на уровне запроса |

```php
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Retry
{
    public function __construct(
        public bool $enabled = true,
        public int $attempts = 3,
        public int $baseDelay = 100,           // мс
        public int $maxDelay = 10000,          // мс
        public BackoffStrategy $backoff = BackoffStrategy::Exponential,
        public bool $jitter = true,
        public array $retryOn = [429, 500, 502, 503, 504],
    ) {}
}
```

**Обработка:** `RetryHandler` — переопределяет ClientConfig.retry.

---

## Timeout

| Атрибут | Target | Описание |
|---------|--------|----------|
| `#[Timeout]` | CLASS | Таймаут запроса |

```php
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Timeout
{
    public function __construct(
        public int $seconds,
        public ?int $connectTimeout = null,
    ) {}
}
```

**Обработка:** `Pipeline` — переопределяет ClientConfig.timeout.

---

## RateLimit

| Атрибут | Target | Описание |
|---------|--------|----------|
| `#[RateLimit]` | CLASS | Лимит запросов для endpoint |

```php
#[Attribute(Attribute::TARGET_CLASS)]
readonly class RateLimit
{
    public function __construct(
        public int $limit,
        public int $period,                    // секунды
        public RateLimitBehavior $behavior = RateLimitBehavior::Wait,
    ) {}
}
```

**Обработка:** `RateLimiter` — переопределяет ClientConfig.rateLimit.

---

## Idempotency

| Атрибут | Target | Описание |
|---------|--------|----------|
| `#[Idempotent]` | CLASS | Автогенерация idempotency key |

```php
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Idempotent
{
    public function __construct(
        public ?string $header = null,  // Override header name
    ) {}
}
```

**Обработка:** `Pipeline` — добавление header.

---

## Execution

| Атрибут | Target | Описание |
|---------|--------|----------|
| `#[Execution]` | CLASS | Настройки выполнения composite |

```php
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Execution
{
    public function __construct(
        public ExecutionMode $mode = ExecutionMode::Sequential,
        public FailStrategy $failStrategy = FailStrategy::FailAll,
    ) {}
}
```

```php
enum ExecutionMode: string
{
    case Sequential = 'sequential';
    case Parallel = 'parallel';
}

enum FailStrategy: string
{
    case FailAll = 'fail_all';       // Любая ошибка → весь composite failed
    case Partial = 'partial';         // Продолжить, вернуть partial
    case IgnoreErrors = 'ignore';     // Игнорировать ошибки
}
```

**Обработка:** `CompositeExecutor` — стратегия выполнения.

---

## Pagination

| Атрибут | Target | Описание |
|---------|--------|----------|
| `#[Pagination]` | CLASS | Настройки пагинации |

```php
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Pagination
{
    public function __construct(
        public string $pageParam = 'page',
        public string $limitParam = 'limit',
        public ?string $cursorParam = null,
        public string $metaPath = 'meta',
        public string $itemsPath = 'data',
        public bool $offsetBased = false,
    ) {}
}
```

**Обработка:** `Paginator` — извлечение страниц.

---

## Files

| Атрибут | Target | Описание |
|---------|--------|----------|
| `#[File]` | PROPERTY | Upload file |
| `#[Download]` | CLASS | Download file response |

```php
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class File
{
    public function __construct(
        public ?string $name = null,    // Имя поля в multipart
    ) {}
}

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Download {}
```

**Обработка:** `Serializer` (multipart), `Hydrator` (stream).

---

## Hooks

| Атрибут | Target | Описание |
|---------|--------|----------|
| `#[BeforeSend(Hook::class)]` | CLASS | Hook перед отправкой |
| `#[AfterResponse(Hook::class)]` | CLASS | Hook после ответа |
| `#[BeforeHydrate(Hook::class)]` | CLASS | Hook перед гидрацией |
| `#[AfterHydrate(Hook::class)]` | CLASS | Hook после гидрации |

```php
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
readonly class BeforeSend
{
    public function __construct(
        public string $handler,
        public HookPriority $priority = HookPriority::Normal,
    ) {}
}
```

```php
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
readonly class AfterResponse
{
    public function __construct(
        public string $handler,
        public HookPriority $priority = HookPriority::Normal,
    ) {}
}
```

```php
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
readonly class BeforeHydrate
{
    public function __construct(
        public string $handler,
        public HookPriority $priority = HookPriority::Normal,
    ) {}
}
```

```php
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
readonly class AfterHydrate
{
    public function __construct(
        public string $handler,
        public HookPriority $priority = HookPriority::Normal,
    ) {}
}
```

**Обработка:** `HookRegistry` — регистрация per-request hooks.

---

## AttributeRegistry

Регистрация кастомных атрибутов.

```php
class AttributeRegistry
{
    public function __construct(
        private readonly Closure $resolver,  // fn(class) => instance
    ) {}
    
    /**
     * Зарегистрировать handler для атрибута
     */
    public function register(
        string $attributeClass,
        string $handlerClass,
    ): void;
    
    /**
     * Получить handler для атрибута
     */
    public function getHandler(string $attributeClass): ?AttributeHandlerInterface;
    
    /**
     * Обработать все атрибуты на объекте
     */
    public function process(
        object $target,
        PipelineContext $context,
    ): void;
}
```

---

## AttributeContextType

Enum для определения контекста обработки атрибутов:

```php
use Brahmic\ApiSutra\Enums\Attributes\AttributeContextType;

enum AttributeContextType: string
{
    case Request = 'request';
    case Dto = 'dto';
}
```

Используется в `AttributeContext` для понимания, применяется ли атрибут к Request или DTO.

---

## Резюме по категориям

| Категория | Атрибуты |
|-----------|----------|
| HTTP | `#[Get]`, `#[Post]`, `#[Put]`, `#[Patch]`, `#[Delete]` |
| Mapping | `#[Path]`, `#[Query]`, `#[Body]`, `#[Header]`, `#[Ignore]` |
| Response | `#[Returns]` |
| DTO | `#[From]`, `#[Cast]`, `#[Nested]` |
| Validation | `#[Validate]`, `#[Label]` |
| Cache | `#[Cache]` |
| Auth | `#[NoAuth]` |
| Retry | `#[Retry]`, `#[Timeout]` |
| RateLimit | `#[RateLimit]` |
| Idempotency | `#[Idempotent]` |
| Execution | `#[Execution]` |
| Pagination | `#[Pagination]` |
| Files | `#[File]`, `#[Download]` |
| Hooks | `#[BeforeSend]`, `#[AfterResponse]`, `#[BeforeHydrate]`, `#[AfterHydrate]` |
