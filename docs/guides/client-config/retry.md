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
- `attempts` — число попыток
- `baseDelay` — базовая задержка (мс)
- `maxDelay` — максимум задержки (мс)
- `backoff` — стратегия (constant/linear/exponential)
- `jitter` — случайная прибавка к задержке
- `retryOn` — HTTP‑коды для повторов
- `retryExceptions` — исключения, при которых повторяем
- `totalTimeoutMs` — общий лимит времени на все попытки

## Переопределение на запросе
Используйте `#[Retry]` или runtime‑опции (`withRetry()`/`withoutRetry()`).

Если `retry` не задан в `ClientConfig`, повторы отключены по умолчанию.

## Где детали
- Атрибуты поведения: `docs/guides/attributes/behavior.md`
