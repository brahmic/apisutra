# Retry

Настройки повторных попыток в `RetryConfig` и дефолтной политики.

## Базовая настройка
```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\RetryConfig;
use Brahmic\ApiSutra\Enums\RateLimiting\BackoffStrategy;
use Brahmic\ApiSutra\Exceptions\Transport\ConnectionException;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    retry: new RetryConfig(
        attempts: 3,
        baseDelay: 200,
        maxDelay: 5000,
        backoff: BackoffStrategy::Exponential,
        jitter: true,
        retryOn: [408, 429, 500, 502, 503, 504],
        retryExceptions: [ConnectionException::class],
        totalTimeoutMs: 10_000,
    ),
);
```

## Параметры RetryConfig
- `attempts` — максимум основных HTTP-попыток, включая первую
- `baseDelay` — базовая задержка (мс)
- `maxDelay` — максимум задержки (мс)
- `backoff` — стратегия (constant/linear/exponential)
- `jitter` — случайная прибавка к задержке
- `retryOn` — HTTP‑коды для повторов
- `retryExceptions` — исключения, при которых повторяем
- `totalTimeoutMs` — проверка времени перед попыткой; пока не ограничивает HTTP-вызов
  и все ожидания, полноценный deadline не гарантируется
- `safeMethods` — необязательный список `HttpMethod`, по умолчанию GET/PUT/DELETE;
  переданный список заменяет стандартный, пустой список запрещает повтор без разрешения
  на классе запроса

## Переопределение на запросе
Используйте `#[Retry]` или runtime‑опции (`withRetry()`/`withoutRetry()`).

`#[Retry(safe: true/false)]` переопределяет безопасность операции относительно
safeMethods клиента. Отсутствующий safe и safe: null наследуют конфиг. Runtime
withRetry меняет число попыток, сохраняя остальные настройки; безопасность POST/PATCH
его вызовом не подтверждается. `#[Retry(enabled: false)]` отключает общие повторы,
runtime может явно переопределить это значение.

Полный контракт и миграция: [Retry и Rate Limit](../retries-rate-limit.md).

Если `retry` не задан в `ClientConfig`, повторы отключены по умолчанию.

## Где детали
- Атрибуты поведения: `docs/guides/attributes/behavior.md`
