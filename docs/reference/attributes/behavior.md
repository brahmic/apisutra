# Атрибуты поведения

## Сигнатуры и targets

Имена классов относятся к `Brahmic\ApiSutra\Attributes\Behavior`.
Сигнатуры показывают параметры и defaults конструктора; target указывает допустимое
место атрибута. Поведение и приоритеты описаны в тематических ссылках ниже.

| Атрибут | Target | Конструктор |
| --- | --- | --- |
| Cache | CLASS | `Cache(?int $ttl = null, CacheMode $mode = CacheMode::Enabled, ?string $key = null)` |
| Execution | CLASS | `Execution(ExecutionMode $mode = ExecutionMode::Sequential, FailStrategy $failStrategy = FailStrategy::FailAll)` |
| Idempotent | CLASS | `Idempotent(?string $header = null)` |
| NoAuth | CLASS | `NoAuth()` |
| Pagination | CLASS | `Pagination(?string $pageParam = null, ?string $limitParam = null, ?string $cursorParam = null, ?string $metaPath = null, ?string $itemsPath = null, ?bool $offsetBased = null, ?string $metaResolver = null, ?string $itemsType = null, ?string $itemsCollection = null, ?string $itemsCollectionFactory = null, ?int $maxPages = null)` |
| RateLimit | CLASS | `RateLimit(?int $limit = null, ?int $period = null, RateLimitBehavior $behavior = RateLimitBehavior::Wait, ?string $key = null, ?bool $includeClientQuota = null)` |
| Retry | CLASS | `Retry(bool $enabled = true, int $attempts = 3, int $baseDelay = 100, int $maxDelay = 10000, BackoffStrategy $backoff = BackoffStrategy::Exponential, bool $jitter = true, array $retryOn = [429, 500, 502, 503, 504], ?bool $safe = null)` |
| Timeout | CLASS | `Timeout(int $seconds, ?int $connectTimeout = null)` |

Атрибуты поведения запроса. Применяются к классу запроса и переопределяют дефолты ClientConfig.

## Когда использовать
- **Cache** — запрос часто повторяется и результат можно кешировать.
- **Retry** — временные ошибки (сеть/5xx/429).
- **Timeout** — задать локальные лимиты на конкретный запрос.
- **RateLimit** — защитить провайдера от частых вызовов.
- **Execution** — управлять batch/composite поведением.
- **Idempotent** — передача ключа идемпотентности для API, который его поддерживает.
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
режим не Disabled. Подробности и миграция: [Cache](../execution/cache.md).

## Retry
**Параметры:**
- `enabled: bool = true`
- `attempts: int = 3`
- `baseDelay: int = 100`
- `maxDelay: int = 10000`
- `backoff: BackoffStrategy = Exponential`
- `jitter: bool = true`
- `retryOn: array = [429, 500, 502, 503, 504]`
- `safe: ?bool = null` — разрешение безопасности операции; null оставляет решение условной политике запроса, затем конфигу клиента

Пример:
```php
use Brahmic\ApiSutra\Attributes\Behavior\Retry;
use Brahmic\ApiSutra\Enums\RateLimiting\BackoffStrategy;

#[Retry(attempts: 5, baseDelay: 200, backoff: BackoffStrategy::Exponential)]
final class RetryRequest extends AbstractRequest {}
```

`safe` необязателен: его отсутствие и явный null равнозначны. true/false перекрывают
RetrySafetyPolicyInterface запроса и safeMethods клиента; true не обходит enabled=false,
лимит попыток или неповторяемое тело. Без интерфейса null наследует конфиг клиента.
`enabled: false` отключает общие повторы, runtime-настройка имеет приоритет.
Подробнее: [политика безопасности](../execution/retry.md#безопасность-повторов-без-обязательной-настройки).

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

Единицы — секунды. Неуказанный/null connect timeout наследует конфиг, 0 отключает
соответствующий SDK-лимит. Runtime имеет приоритет над атрибутом. Полный контракт:
[Timeouts & Delay](../execution/deadlines.md).

## RateLimit

Собственная квота операции действует совместно с `ClientConfig::rateLimit`.
[Полный контракт и defaults](../execution/rate-limit.md).

Параметры:

- `limit: ?int = null`, `period: ?int = null` — положительная пара, период в секундах;
- `behavior: RateLimitBehavior = Wait`;
- `key: ?string = null` — явная группа операций;
- `includeClientQuota: ?bool = null` — наследует клиентский флаг (по умолчанию true).

```php
use Brahmic\ApiSutra\Attributes\Behavior\RateLimit;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[RateLimit(limit: 10, period: 60)]
final class RateLimitedRequest extends AbstractRequest {}

#[RateLimit(includeClientQuota: false)]
final class IndependentRequest extends AbstractRequest {}
```

Без пары собственная квота не создаётся. Пустой атрибут разрешён; key или Throw
без пары — ошибка конфигурации. Проверка значений выполняется при применении перед
отправкой; чтение метаданных само по себе её не запускает.

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

Атрибут `Cache` задаёт параметры HTTP-кеша; при отсутствии `ClientConfig.cacheConfig` или его store
он не создаёт и не восстанавливает хранилище. См. [подключение кеша](../execution/cache.md).
