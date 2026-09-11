# Retry и Rate Limiting

## Обзор

SDK обеспечивает устойчивость к сбоям и соблюдение лимитов API.

Retry реализован на уровне pipeline (RetryHandler); Transport выполняет
одиночный HTTP‑запрос и возвращает `ProviderResponse`.

RetryHandler работает после получения `ProviderResponse` и проверки ошибок,
и повторяет только участок Transport → afterResponse → error‑check.
Hydration и `afterHydrate` выполняются один раз для финального ответа.

**Уровни конфигурации (приоритет снизу вверх):**
```
Клиент (defaults) → Запрос (атрибут) → Рантайм (метод)
```

---

## Конфигурация через VO

Настройки группируются в отдельные Value Objects для чистоты конфига:

```php
$client = new Client(
    config: new ClientConfig(
        baseUrl: 'https://api.example.com',
        
        delay: 100,  // мс между запросами (throttling)
        
        retry: new RetryConfig(
            attempts: 3,
            baseDelay: 1000,
            maxDelay: 10000,
            backoff: BackoffStrategy::Exponential,
            jitter: true,
            retryOn: [408, 429, 500, 502, 503, 504],
        ),
        
        rateLimit: new RateLimitConfig(
            limit: 100,
            period: 60,
            behavior: RateLimitBehavior::Wait,
            store: $psrCache,  // опционально, для distributed
        ),
    )
);
```

---

## Delay (задержка между запросами)

Задержка перед каждым запросом. Полезно для "вежливого" обращения к API.

### ClientConfig

```php
$config = new ClientConfig(
    delay: 500,  // 500мс перед каждым запросом
);
```

### Runtime

```php
$request->withDelay(1000)->send();  // 1 секунда задержки
$request->withoutDelay()->send();   // без задержки
```

**Отличие от Rate Limiting:**
- **Delay** — фиксированная пауза перед каждым запросом
- **Rate Limiting** — контроль количества запросов за период
```

---

## Retry (повторные попытки)

### RetryConfig

```php
readonly class RetryConfig
{
    public function __construct(
        public int $attempts = 3,
        public int $baseDelay = 100,                           // мс
        public int $maxDelay = 10000,                          // мс
        public BackoffStrategy $backoff = BackoffStrategy::Exponential,
        public bool $jitter = true,
        public array $retryOn = [408, 429, 500, 502, 503, 504],
        public array $retryExceptions = [ConnectionException::class],
    ) {}
}
```

### BackoffStrategy

```php
enum BackoffStrategy: string
{
    case Constant = 'constant';       // всегда baseDelay
    case Linear = 'linear';           // baseDelay * attempt
    case Exponential = 'exponential'; // baseDelay * 2^attempt
}
```

**Пример с Exponential (baseDelay: 1000ms):**
```
Попытка 1: 1000ms
Попытка 2: 2000ms
Попытка 3: 4000ms
```

### Jitter

Добавляет случайность к задержке, предотвращая "thundering herd":

```
Без jitter:
  Client A: retry at 1s, 2s, 4s
  Client B: retry at 1s, 2s, 4s
  → Все бьют сервер одновременно

С jitter:
  Client A: retry at 1.2s, 2.4s, 4.1s
  Client B: retry at 0.9s, 1.8s, 3.7s
  → Нагрузка распределена
```

**Формула:**
```php
$finalDelay = $calculatedDelay + random(0, $baseDelay * 0.5);
```

### retryOn — HTTP статусы

`retryOn` задаётся массивом HTTP‑статусов. Примеры:
- `retryOn: [408, 429, 500, 502, 503, 504]`
- `retryOn: [429]` — только rate limit

### shouldRetry() — кастомная логика

Retry по содержимому ответа (не только HTTP коду). Определяется в клиенте или запросе.

```php
class KonturClient extends AbstractClient
{
    protected function shouldRetry(ProviderResponse $response, int $attempt): bool
    {
        $status = $response->json('status');
        
        return in_array($status, [
            'waiting_for_response',  // провайдер ещё обрабатывает
            'source_unavailable',    // источник временно недоступен
        ]);
    }
}
```

**Приоритет retry:**
1. `shouldRetry()` — кастомная логика по body
2. `RetryableException` — из getRequestException()
3. `retryOn` — HTTP коды (429, 5xx)

См. [Исключения](./exceptions.md) для деталей.

### Уровень запроса (атрибут)

```php
// Больше попыток для нестабильного API
#[Retry(attempts: 5, baseDelay: 2000)]
class SlowExternalApi extends AbstractRequest { }

// Отключить retry
#[Retry(enabled: false)]
class IdempotentCheck extends AbstractRequest { }

// Кастомные коды
#[Retry(retryOn: [429, 503])]
class SpecificApi extends AbstractRequest { }
```

### Рантайм

```php
$request->withRetry(attempts: 1)->send();
$request->withoutRetry()->send();
```

---

## Rate Limiting

### RateLimitConfig

```php
readonly class RateLimitConfig
{
    public function __construct(
        public int $limit = 100,
        public int $period = 60,                                  // секунды
        public RateLimitBehavior $behavior = RateLimitBehavior::Wait,
        public ?CacheInterface $store = null,                     // для distributed
    ) {}
}
```

### RateLimitBehavior

```php
enum RateLimitBehavior: string
{
    case Wait = 'wait';  // ждать освобождения слота (default)
    case Throw = 'throw';  // сразу ошибка RateLimitExceeded
}
```

### Реактивный (обработка 429)

Встроен в Retry. При получении 429:
1. Читает заголовок `Retry-After`
2. Ждёт указанное время
3. Повторяет запрос

**Примечание про 401:** если включён `authRetryOn401`, 401 обрабатывается
через refresh токена; общий retry не применяется к 401.

```php
$config = new ClientConfig(
    retry: new RetryConfig(
        attempts: 3,
        backoff: BackoffStrategy::Exponential,
    ),
);
```

### Проактивный (throttling)

SDK контролирует частоту запросов до отправки:

```php
$config = new ClientConfig(
    rateLimit: new RateLimitConfig(
        limit: 100,   // запросов
        period: 60,   // за период (секунды)
        behavior: RateLimitBehavior::Wait,
    ),
);
```

### Уровень запроса (атрибут)

Для endpoint со своими лимитами:

```php
#[RateLimit(limit: 10, period: 60)]
class HeavyReport extends AbstractRequest { }

#[RateLimit(limit: 100, period: 60)]
class LightQuery extends AbstractRequest { }
```

### Рантайм

```php
$request->withRateLimit(limit: 5, period: 10)->send();
$request->withoutRateLimit()->send();  // bypass throttling
```

### Хранение счётчика

**In-memory (default):**
Счётчик в памяти клиента. Работает для singleton-клиентов.

**Внешний store (для distributed):**
```php
$config = new ClientConfig(
    rateLimit: new RateLimitConfig(
        store: $redisCache,  // PSR-16 совместимый
    ),
);
```

---

## Idempotency Keys

Защита от дубликатов при retry мутирующих запросов.

### Сценарий проблемы

1. Клиент отправляет `CreateOrder`
2. Сервер создаёт заказ #123
3. Ответ не доходит (сеть)
4. Клиент делает retry
5. Сервер создаёт заказ #124 (дубликат!)

### Решение

```php
// Атрибут — SDK генерирует уникальный ключ
#[Post('/orders')]
#[Idempotent]
class CreateOrder extends AbstractRequest { }

// С кастомным header name
#[Idempotent(header: 'X-Idempotency-Key')]
class CreatePayment extends AbstractRequest { }
```

### Runtime

```php
$request->withIdempotencyKey('my-unique-key-123')->send();
```

### Генерация ключа

По умолчанию: `hash(class + properties + timestamp)`

### Конфигурация

```php
$config = new ClientConfig(
    idempotencyHeader: 'Idempotency-Key', // default header name
);
```

**Важно:** Работает только если API поддерживает idempotency keys.

---

## Методы AbstractRequest

```php
// Delay
public function withDelay(int $ms): static;
public function withoutDelay(): static;

// Retry
public function withRetry(int $attempts): static;
public function withoutRetry(): static;

// Rate Limit
public function withRateLimit(int $limit, int $period): static;
public function withoutRateLimit(): static;

// Idempotency
public function withIdempotencyKey(string $key): static;
```

## Методы для переопределения

```php
// В AbstractClient или AbstractRequest
protected function shouldRetry(ProviderResponse $response, int $attempt): bool;
```

---

## Примеры

### Базовое использование

```php
$client = new Client(
    config: new ClientConfig(
        baseUrl: 'https://api.example.com',
        retry: new RetryConfig(attempts: 3),
        rateLimit: new RateLimitConfig(limit: 100, period: 60),
    )
);

// Retry и rate limit работают автоматически
$result = $client->orders()->get('123')->send();
```

### Критичный запрос без retry

```php
$result = $client->payments()->charge($data)
    ->withoutRetry()
    ->send();
```

### Мутирующий запрос с idempotency

```php
#[Post('/orders')]
#[Idempotent]
class CreateOrder extends AbstractRequest { }

// SDK автоматически добавляет Idempotency-Key header
$result = $client->orders()->create($data)->send();
```

### Тяжёлый endpoint

```php
#[RateLimit(limit: 5, period: 60)]
#[Retry(attempts: 5, baseDelay: 5000)]
class GenerateReport extends AbstractRequest { }
```

---

## Composite и Batch

- **Composite:** retry и rate limiting применяются к каждому дочернему запросу
  по его собственным правилам. Для общего поведения используйте defaults клиента.
- **Batch:** каждый запрос внутри batch обрабатывается как обычный запрос
  со своим retry/rate limit.

---

## Резюме

| Элемент | Назначение |
|---------|------------|
| `delay` | Фиксированная задержка между запросами |
| `RetryConfig` | VO конфигурации retry |
| `RateLimitConfig` | VO конфигурации rate limiting |
| `BackoffStrategy` | Стратегия увеличения задержки |
| `jitter` | Случайность для распределения нагрузки |
| `retryOn` | Условия для retry (HTTP коды) |
| `shouldRetry()` | Кастомная логика retry (по body) |
| `RateLimitBehavior` | Поведение при лимите |
| `#[Idempotent]` | Защита от дубликатов |

## Связанные документы

- [Исключения](./exceptions.md) — `shouldRetry()`, `RetryableException`
