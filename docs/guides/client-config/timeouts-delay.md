# Timeouts & Delay

Настройки таймаутов и задержек в `ClientConfig`.

## Таймауты
```php
use Brahmic\ApiSutra\Config\ClientConfig;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    timeout: 30,
    connectTimeout: 10,
);
```

По умолчанию: `timeout = 30`, `connectTimeout = 10`.
Уменьшайте таймауты, если API быстро отвечает и вам важна реактивность.

## Задержка
```php
$config = new ClientConfig(
    baseUrl: 'https://api.example',
    delay: 200, // мс
);
```

По умолчанию `delay = 0`. Используйте задержку, если у провайдера есть
неявные лимиты и нужно «смягчить» нагрузку.

## Переопределение на запросе
`#[Timeout]` или runtime‑опция `withTimeout()`.  
`withDelay()` / `withoutDelay()` — для задержек.
