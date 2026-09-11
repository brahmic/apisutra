# Behavior attributes

Атрибуты поведения запроса. Применяются к классу запроса и переопределяют дефолты ClientConfig.

## Когда использовать
- **Cache** — запрос часто повторяется и результат можно кешировать.
- **Retry** — временные ошибки (сеть/5xx/429).
- **Timeout** — задать локальные лимиты на конкретный запрос.
- **RateLimit** — защитить провайдера от частых вызовов.
- **Execution** — управлять batch/composite поведением.
- **Idempotent** — операции, которые можно безопасно повторять.
- **NoAuth** — публичные или сервисные запросы без auth.
- **Pagination** — переопределить pagination‑настройки запроса.

## Cache
**Параметры:**  
- `ttl?: int`  
- `mode: CacheMode = Enabled`  
- `key?: string`  

Пример:
```php
use Brahmic\ApiSutra\Attributes\Behavior\Cache;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Cache\CacheMode;

#[Cache(ttl: 60, mode: CacheMode::Enabled)]
final class CachedRequest extends AbstractRequest {}
```

`key` объединяет варианты запроса внутри автоматического пространства identity/tenant.
`CacheConfig::prefix` и `withCacheScope()` служат дополнительному разделению и
не обязательны. При неопределённой identity HTTP-кеш
не используется. Атрибут разрешает кеширование операции, включая POST, если итоговый
режим не Disabled. Подробности и миграция: [Cache](../client-config/cache.md).

## Retry
**Параметры:**  
- `enabled: bool = true`  
- `attempts: int = 3`  
- `baseDelay: int = 100`  
- `maxDelay: int = 10000`  
- `backoff: BackoffStrategy = Exponential`  
- `jitter: bool = true`  
- `retryOn: array = [429, 500, 502, 503, 504]`  

Пример:
```php
use Brahmic\ApiSutra\Attributes\Behavior\Retry;
use Brahmic\ApiSutra\Enums\RateLimiting\BackoffStrategy;

#[Retry(attempts: 5, baseDelay: 200, backoff: BackoffStrategy::Exponential)]
final class RetryRequest extends AbstractRequest {}
```

## Timeout
**Параметры:**  
- `seconds: int`  
- `connectTimeout?: int`  

Пример:
```php
use Brahmic\ApiSutra\Attributes\Behavior\Timeout;

#[Timeout(seconds: 5, connectTimeout: 2)]
final class TimeoutRequest extends AbstractRequest {}
```

## RateLimit
**Параметры:**  
- `limit: int`  
- `period: int`  
- `behavior: RateLimitBehavior = Wait`  
- `key?: string`  

Пример:
```php
use Brahmic\ApiSutra\Attributes\Behavior\RateLimit;
use Brahmic\ApiSutra\Enums\RateLimiting\RateLimitBehavior;

#[RateLimit(limit: 10, period: 60, behavior: RateLimitBehavior::Wait)]
final class RateLimitedRequest extends AbstractRequest {}
```

## Execution
**Параметры:**  
- `mode: ExecutionMode = Sequential`  
- `failStrategy: FailStrategy = FailAll`  

Пример:
```php
use Brahmic\ApiSutra\Attributes\Behavior\Execution;
use Brahmic\ApiSutra\Enums\Execution\ExecutionMode;
use Brahmic\ApiSutra\Enums\Execution\FailStrategy;

#[Execution(mode: ExecutionMode::Parallel, failStrategy: FailStrategy::Partial)]
final class ParallelRequest extends AbstractRequest {}
```

## Idempotent
**Параметры:**  
- `header?: string` — имя заголовка идемпотентности  

Пример:
```php
use Brahmic\ApiSutra\Attributes\Behavior\Idempotent;

#[Idempotent(header: 'Idempotency-Key')]
final class IdempotentRequest extends AbstractRequest {}
```

## NoAuth
**Параметры:** нет  
**Эффект:** отключает авторизацию для запроса

Пример:
```php
use Brahmic\ApiSutra\Attributes\Behavior\NoAuth;

#[NoAuth]
final class PublicRequest extends AbstractRequest {}
```

## Pagination
**Параметры (override для PaginationConfig):**  
- `pageParam?: string`, `limitParam?: string`, `cursorParam?: string`  
- `metaPath?: string`, `itemsPath?: string`  
- `offsetBased?: bool`  
- `metaResolver?: string`  
- `itemsType?: string`  
- `itemsCollection?: string`  
- `itemsCollectionFactory?: string`  
- `maxPages?: int`  

По умолчанию берутся настройки из `ClientConfig::paginationConfig`.
Если конфиг не задан, используются дефолты:
- `pageParam = page`, `limitParam = limit`, `cursorParam = null`
- `metaPath = meta`, `itemsPath = data`
- `offsetBased = false`, `maxPages = 1000`

Пример:
```php
use Brahmic\ApiSutra\Attributes\Behavior\Pagination;

#[Pagination(itemsPath: 'data.items', metaPath: 'meta')]
final class PaginatedRequest extends AbstractRequest {}
```
