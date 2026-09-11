# Rate Limit

Настройка rate‑limit через `RateLimitConfig`.

## Базовая настройка
```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\RateLimitConfig;
use Brahmic\ApiSutra\Enums\RateLimiting\RateLimitBehavior;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    rateLimit: new RateLimitConfig(
        limit: 10,
        period: 60,
        behavior: RateLimitBehavior::Wait,
    ),
);
```

## Переопределение на запросе
`#[RateLimit]` или runtime‑опции `withRateLimit()/withoutRateLimit()`.

## Ключ и store
- `RateLimitConfig::key` позволяет задать собственный ключ лимита.
- без ключа используется `baseUrl`, чтобы лимитировать на уровне провайдера.
- `store` (PSR‑16) делает лимит межпроцессным; без store лимит локальный.

## Поведение
- `RateLimitBehavior::Wait` — ждать до конца окна
- `RateLimitBehavior::Throw` — бросать `RateLimitException`
