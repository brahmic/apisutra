# Config — Конфигурация клиента

Все настройки SDK через Value Objects.

---

## ClientConfig

Главный конфигурационный объект.

```php
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Configuration\NamingStrategy;
use Brahmic\ApiSutra\Enums\Http\QueryArrayFormat;
use Psr\Log\LogLevel;

readonly class ClientConfig
{
    public function __construct(
        // === Обязательные ===
        public string $baseUrl,
        
        // === Аутентификация ===
        public ?AuthenticatorInterface $auth = null,
        public bool $authRetryOn401 = true,     // повторить запрос при 401
        public int $authRetryAttempts = 1,      // попыток refresh токена
        
        // === Инфраструктура ===
        public ?LoggerInterface $logger = null,
        public string $logLevel = LogLevel::INFO,
        public ?CacheInterface $cache = null,
        
        // === Таймауты ===
        public int $timeout = 30,              // секунды
        public int $connectTimeout = 10,       // секунды
        
        // === Retry ===
        public ?RetryConfig $retry = null,
        
        // === Rate Limiting ===
        public ?RateLimitConfig $rateLimit = null,
        
        // === Кеширование ===
        public ?CacheConfig $cacheConfig = null,
        
        // === Pool ===
        public ?PoolConfig $pool = null,
        
        // === Сериализация ===
        public QueryArrayFormat $queryArrayFormat = QueryArrayFormat::Brackets,
        public bool $serializeNulls = false,
        public NamingStrategy $namingStrategy = NamingStrategy::None,
        
        // === Типы ===
        public array $casts = [],              // [Type::class => Cast::class]
        
        // === Поведение ===
        public int $delay = 0,                 // мс между запросами
        public bool $throwOnErrors = false,    // автоматический throw
        public bool $debug = false,            // debug info в результате
        public Environment $environment = Environment::Production,
        
        // === Idempotency ===
        public string $idempotencyHeader = 'Idempotency-Key',

        // === Pagination ===
        public ?PaginationConfig $paginationConfig = null,
        
        // === Extensions ===
        public array $extensions = [],          // array<ExtensionInterface>

        // === Архивы ===
        public ?ArchiveConfig $archive = null,
    ) {}
    
    /**
     * Создать с переопределениями
     */
    public function with(mixed ...$overrides): self;
}
```

`authRetryOn401` включает автоматический refresh и повтор при 401.
`authRetryAttempts` ограничивает количество попыток refresh.
`environment` управляет режимами `AttributeMetadataCache` (см. DTO System).
`archive` (`ArchiveConfig`) задаёт работу с temp‑файлами архивов (`ArchiveResponse`).

---

## ArchiveConfig

```php
readonly class ArchiveConfig
{
    public function __construct(
        public string $driver = 'native',         // native|spatie|auto
        public ?string $tempDir = null,           // путь для temp‑файлов
        public ?int $maxSize = null,              // лимит размера архива
        public ?TempDirectoryProviderInterface $tempProvider = null,
    ) {}
}
```

---

## RetryConfig

Настройки повторных попыток.

```php
use Brahmic\ApiSutra\Enums\RateLimiting\BackoffStrategy;

readonly class RetryConfig
{
    public function __construct(
        public int $attempts = 3,
        public BackoffStrategy $backoff = BackoffStrategy::Exponential,
        public int $baseDelay = 100,           // мс
        public int $maxDelay = 10000,          // мс
        public bool $jitter = true,
        public array $retryOn = [              // HTTP статусы
            408, 429, 500, 502, 503, 504,
        ],
        public array $retryExceptions = [      // Exception classes
            ConnectionException::class,
        ],
    ) {}
}
```

### BackoffStrategy

```php
enum BackoffStrategy: string
{
    case Constant = 'constant';      // Фиксированная задержка
    case Linear = 'linear';          // delay * attempt
    case Exponential = 'exponential'; // delay * 2^attempt
}
```

---

## RateLimitConfig

Настройки лимитов запросов.

```php
use Brahmic\ApiSutra\Enums\RateLimiting\RateLimitBehavior;

readonly class RateLimitConfig
{
    public function __construct(
        public int $limit = 100,               // Количество запросов
        public int $period = 60,               // Период в секундах
        public RateLimitBehavior $behavior = RateLimitBehavior::Wait,
        public ?CacheInterface $store = null,
    ) {}
}
```

### RateLimitBehavior

```php
enum RateLimitBehavior: string
{
    case Wait = 'wait';   // Ждать освобождения слота
    case Throw = 'throw'; // Выбросить RateLimitException
}
```

---

## CacheConfig

Настройки кеширования.

```php
use Brahmic\ApiSutra\Enums\Cache\CacheMode;

readonly class CacheConfig
{
    public function __construct(
        public ?CacheInterface $store = null,
        public int $ttl = 3600,                // секунды
        public string $prefix = '',
        public CacheMode $mode = CacheMode::Enabled,
    ) {}
}
```

---

## PoolConfig

Настройки для массовых запросов.

```php
readonly class PoolConfig
{
    public function __construct(
        public int $concurrency = 5,
        public bool $stopOnFailure = false,
    ) {}
}
```

---

## QueryArrayFormat

Формат массивов в query string.

```php
enum QueryArrayFormat: string
{
    case Brackets = 'brackets';     // items[]=1&items[]=2
    case Indices = 'indices';       // items[0]=1&items[1]=2
    case Comma = 'comma';           // items=1,2
    case Repeat = 'repeat';         // items=1&items=2
}
```

---

## NamingStrategy

Стратегия преобразования имён.

```php
enum NamingStrategy: string
{
    case None = 'none';            // Без преобразования
    case SnakeCase = 'snake_case'; // camelCase → snake_case
}
```

---

## Environment

Окружение приложения. Влияет на поведение SDK.

```php
enum Environment: string
{
    case Local = 'local';
    case Testing = 'testing';
    case Staging = 'staging';
    case Production = 'production';
}
```

**Влияние:**
- `Local/Testing`: verbose logging, no metadata cache
- `Staging`: metadata cache enabled
- `Production`: metadata cache enabled, minimal logging

---

## ErrorCode

Коды ошибок SDK.

```php
enum ErrorCode: string
{
    // Transport
    case ConnectionFailed = 'connection_failed';
    case Timeout = 'timeout';
    case DnsError = 'dns_error';
    
    // HTTP 4xx
    case Unauthorized = 'unauthorized';       // 401
    case Forbidden = 'forbidden';             // 403
    case NotFound = 'not_found';              // 404
    case ValidationFailed = 'validation_failed'; // 422
    case RateLimited = 'rate_limited';        // 429
    
    // HTTP 5xx
    case ServerError = 'server_error';        // 500
    case BadGateway = 'bad_gateway';          // 502
    case ServiceUnavailable = 'service_unavailable'; // 503
    case GatewayTimeout = 'gateway_timeout';  // 504
    
    // SDK internal
    case ConfigurationError = 'configuration_error';
    case HydrationError = 'hydration_error';
    case SerializationError = 'serialization_error';
    case ExtensionError = 'extension_error';
}
```

---

## Каскад конфигурации

Приоритет (от низшего к высшему):

```
1. ClientConfig (defaults)
2. Атрибут на Request (#[Cache], #[Retry])
3. Runtime modifier ($request->withCache(60))
```

**Пример:**

```php
// 1. Client default: TTL 3600
$config = new ClientConfig(
    cacheConfig: new CacheConfig(store: $cache, ttl: 3600),
);

// 2. Request attribute: TTL 300
#[Cache(ttl: 300)]
class GetUser extends AbstractRequest {}

// 3. Runtime: TTL 60
$request->withCache(ttl: 60)->send();  // TTL = 60
```

---

## Валидация конфигурации

```php
// При создании ClientConfig:
// - baseUrl обязателен и должен быть валидным URL
// - timeout > 0
// - retry.attempts >= 0
// - rateLimit.limit > 0, period > 0

// ConfigurationException при невалидных значениях
```

---

## Создание конфигурации в SDK

```php
class MyClient extends AbstractClient
{
    public static function make(string $apiKey): self
    {
        return new self(
            config: new ClientConfig(
                baseUrl: 'https://api.example.com/v1',
                auth: new ApiKeyAuthenticator($apiKey),
                retry: new RetryConfig(attempts: 3),
                rateLimit: new RateLimitConfig(
                    limit: 100,
                    period: 60,
                ),
                namingStrategy: NamingStrategy::SnakeCase,
            ),
            transport: app(TransportInterface::class),
        );
    }
}
```

---

## Резюме

| Config | Назначение |
|--------|------------|
| `ClientConfig` | Главная конфигурация |
| `RetryConfig` | Retry стратегия |
| `RateLimitConfig` | Лимиты запросов |
| `CacheConfig` | Кеширование |
| `PoolConfig` | Массовые запросы |
| `BackoffStrategy` | Алгоритм задержки retry |
| `RateLimitBehavior` | Поведение при лимите |
| `QueryArrayFormat` | Формат arrays в query |
| `NamingStrategy` | Преобразование имён |
| `Environment` | Окружение (влияет на cache, logging) |
| `ErrorCode` | Коды ошибок SDK |
