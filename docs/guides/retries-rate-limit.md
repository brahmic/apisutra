# Retry и Rate Limit

Retry повторяет запросы при ошибках, Rate Limit ограничивает скорость вызовов.
Rate Limit применяется независимо от идемпотентности операции.

## Retry (уровень клиента)
```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\RetryConfig;
use Brahmic\ApiSutra\Enums\RateLimiting\BackoffStrategy;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    retry: new RetryConfig(
        attempts: 3,
        baseDelay: 200,
        maxDelay: 5000,
        backoff: BackoffStrategy::Exponential,
        jitter: true,
        retryOn: [408, 429, 500, 502, 503, 504],
    ),
);
```

Дополнительно:
- `retryExceptions` — повтор при исключениях (например, ConnectionException)
- `totalTimeoutMs` — общий таймаут всех попыток

## Retry для конкретного запроса
```php
use Brahmic\ApiSutra\Attributes\Behavior\Retry;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Retry(attempts: 5, backoff: BackoffStrategy::Linear)]
final class GetUsers extends AbstractRequest {}
```

Runtime‑override: `withRetry()` и `withoutRetry()`.  
Включайте retry, когда контракт API подтверждает безопасность повтора: операция
идемпотентна или провайдер поддерживает ключ идемпотентности для неё.
SDK не проверяет эти гарантии API автоматически; наличие `#[Idempotent]`
само по себе не подтверждает поддержку ключа провайдером.

### RetryableException и Retry‑After
- `RetryableException` может быть выброшен в кастомной логике и принудить retry.
- При 429 SDK читает `Retry-After` и делает паузу перед повтором.

## Delay
Фиксированная задержка между запросами:
```php
$config = new ClientConfig(
    baseUrl: 'https://api.example',
    delay: 200, // мс
);
```

Runtime‑override: `withDelay()` и `withoutDelay()`.

### SleeperInterface
Delay и retry используют `SleeperInterface`. Для event‑loop окружений
можно передать non‑blocking реализацию в конструктор клиента.

По умолчанию используется `SystemSleeper` (blocking sleep).
Переопределение нужно для асинхронных/event‑loop окружений,
где blocking sleep недопустим.

## Rate Limit (уровень клиента)
```php
use Brahmic\ApiSutra\Config\RateLimitConfig;
use Brahmic\ApiSutra\Enums\RateLimiting\RateLimitBehavior;

$config = $config->with(rateLimit: new RateLimitConfig(
    limit: 60,
    period: 60,
    behavior: RateLimitBehavior::Wait,
));
```

## Rate Limit для конкретного запроса
```php
use Brahmic\ApiSutra\Attributes\Behavior\RateLimit;

#[RateLimit(limit: 10, period: 1, behavior: RateLimitBehavior::Throw)]
final class Search extends AbstractRequest {}
```

Runtime‑override: `withRateLimit()` и `withoutRateLimit()`.  
Для межпроцессного лимита задайте `RateLimitConfig::store` (PSR‑16).

### Ключ лимита
По умолчанию ключ строится из `baseUrl`. Можно переопределить через `RateLimitConfig::key`
или атрибут `#[RateLimit(key: ...)]`.

Переопределяйте ключ, если один и тот же `baseUrl` используется для разных
тенантов/пользователей или нужно разделить лимиты по операциям.

## Идемпотентность
```php
use Brahmic\ApiSutra\Attributes\Behavior\Idempotent;

#[Idempotent]
final class CreateOrder extends AbstractRequest {}
```

Нюансы:
- заголовок по умолчанию берётся из `ClientConfig::idempotencyHeader`
- можно переопределить через `#[Idempotent(header: 'X-Idempotency')]`
- runtime‑ключ: `withIdempotencyKey('...')`
- если ключ не задан, SDK генерирует его автоматически

Меняйте заголовок, если провайдер ожидает другое имя или уже использует свой стандарт.

### 401 и refresh
Если `authRetryOn401 = true`, при 401 сначала выполняется refresh токена,
и только затем retry.

## Где детали
- `docs/guides/attributes/behavior.md` — `Retry`, `RateLimit`, `Idempotent`
- `docs/guides/client-config/retry.md`
- `docs/guides/client-config/rate-limit.md`
