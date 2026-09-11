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
- `totalTimeoutMs` — необязательный общий бюджет pipeline, включая HTTP, авторизацию
  и ожидания. `withoutRetry()` его не отменяет. Исчерпание окончательно; контракт и
  границы гарантии — в [Timeouts & Delay](client-config/timeouts-delay.md).

### Безопасность повторов без обязательной настройки

Общие повторы по умолчанию выключены (`ClientConfig::retry = null`). При их включении
без дополнительных настроек безопасности разрешены GET, PUT и DELETE. POST/PATCH
требуют явного подтверждения безопасности операции. Настроенный статус, исключение
или число попыток сами по себе такого разрешения не дают.

Для отдельного API можно изменить список на уровне клиента:

```php
use Brahmic\ApiSutra\Enums\Http\HttpMethod;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    retry: new RetryConfig(safeMethods: [HttpMethod::GET]),
);
```

`safeMethods` необязателен; по умолчанию это GET/PUT/DELETE. Переданный список заменяет
стандартный, а пустой список запрещает повторы без разрешающего атрибута запроса.
Добавляйте POST/PATCH только при гарантии безопасности этих методов для данного API.

На классе запроса `#[Retry(safe: true)]` разрешает повтор, `safe: false` запрещает.
Неуказанный `safe` и явный `safe: null` равнозначны: применяется политика клиента.
Без атрибута безопасность также определяется конфигом. Разрешение операции не
обходит лимит попыток, отключение retry или невозможность восстановить тело.

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
SDK не проверяет гарантии внешнего API автоматически. `#[Idempotent]` и наличие
заголовка Idempotency-Key не являются разрешением повтора; безопасность явно
объявляется через `safe` запроса или `safeMethods` клиента.

`attempts` включает первую основную HTTP-попытку. `#[Retry(enabled: false)]` и
`withoutRetry()` отключают общие повторы; runtime имеет приоритет над атрибутом.
`withRetry(attempts)` сохраняет остальные настройки клиента/атрибута, включая
`safeMethods`, `retryExceptions` и `totalTimeoutMs`. Runtime-методы возвращают отдельный
execution: используйте возвращённое значение, исходный запрос не изменяется.

### RetryableException и Retry‑After
- `RetryableException` запрашивает повтор, но не обходит выключенный retry, безопасность,
  воспроизводимость тела и лимит попыток. `maxAttempts` может дополнительно уменьшить
  лимит; `retryAfter` задаётся в секундах. При невозможном повторе исключение сохраняется.
- Встроенный обработчик не делает backoff перед первой попыткой. Первый повтор
  использует baseDelay, следующие — выбранную стратегию и jitter с ограничением maxDelay.
- Для 429 и 503 учитывается `Retry-After`: целые неотрицательные секунды или HTTP-date.
  Дата в прошлом даёт 0; отрицательные, дробные, некорректные и выходящие за безопасный
  числовой диапазон значения игнорируются. Встроенное ожидание равно
  `max(backoff, Retry-After)`. maxDelay ограничивает backoff, а не серверное ожидание.
- При исчерпании попыток или отказе в повторе retry-ожидания нет.

Перед дополнительной отправкой SDK автоматически восстанавливает тело. Если это
невозможно, сохраняются исходный ответ/исключение и диагностическая причина
`retryRefusalReason` в контексте ошибки и warning-логе: `operation_not_safe`,
`body_not_replayable`, `body_rewind_failed` или `body_changed`.
Подробности: [повторы файлов](files.md#повторная-отправка).

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
Delay и встроенный retry используют `SleeperInterface`, который можно передать
в конструктор клиента. По умолчанию `SystemSleeper` блокирует текущий поток выполнения.
Подмена sleeper удобна для тестов, но сама по себе не делает promise API или HTTP I/O
неблокирующими. Собственный `RetryHandlerInterface` сохраняет ответственность за свой
backoff; pipeline отдельно выдерживает Retry-After. Объединение в максимум гарантируется
встроенным RetryHandler, собственный handler не должен повторно ждать Retry-After.

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
- если ключ не задан, SDK генерирует его автоматически один раз на выполнение;
  все попытки используют один ключ, независимые выполнения получают разные ключи
- пользовательский ключ, включая уже переданный заголовок, сохраняется

Меняйте заголовок, если провайдер ожидает другое имя или уже использует свой стандарт.

### 401 и refresh
Если `authRetryOn401 = true`, SDK после 401 пробует повторную аутентификацию и отправку
в пределах отдельного authRetryAttempts. `withoutRetry()` самостоятельно не отключает
этот механизм. Проверки безопасности операции и тела действуют и здесь: POST/PATCH
требуют явного разрешения. Auth retry не добавляет обычный backoff.
Текущий механизм ещё не исключает повтор с неизменяемыми credentials, когда refresh
недоступен; это ограничение не следует принимать за гарантию обновления токена.

## Где детали
- `docs/guides/attributes/behavior.md` — `Retry`, `RateLimit`, `Idempotent`
- `docs/guides/client-config/retry.md`
- `docs/guides/client-config/rate-limit.md`
