# Настройки выполнения

## Кеширование

### Cache (атрибут)
Атрибут конфигурации кеширования на уровне запроса. Параметры: mode (CacheMode), ttl (время жизни). TTL по умолчанию берётся из ClientConfig.

### withoutCache()
Метод AbstractRequest. Выполняет запрос без чтения и записи кеша. Используется для получения свежих данных.

### withCache(?int $ttl)
Метод AbstractRequest. Включает кеширование или переопределяет TTL. TTL опционален — по умолчанию из ClientConfig.

### withCacheWriteOnly(?int $ttl)
Метод AbstractRequest. Записывает результат в кеш без чтения.

### withCacheReadOnly(?int $ttl)
Метод AbstractRequest. Читает из кеша без записи.

### clearCache()
Метод AbstractRequest и AbstractClient. На запросе — удаляет кеш конкретного запроса. На клиенте — очищает весь кеш SDK.

## Таймауты

### Timeout (атрибут)
Атрибут конфигурации таймаута на уровне запроса. Переопределяет значение из ClientConfig для конкретного запроса.

### withTimeout(int $seconds)
Метод AbstractRequest. Переопределяет таймаут в рантайме для конкретного вызова.

## Delay

### withDelay(int $ms)
Метод AbstractRequest. Устанавливает задержку перед выполнением запроса.

### withoutDelay()
Метод AbstractRequest. Отключает задержку для конкретного вызова.

## Retry

### Retry (атрибут)
Атрибут конфигурации retry на уровне запроса. Параметры: attempts, delay, enabled, on. Переопределяет defaults из ClientConfig.

### retryOn
Список HTTP‑статусов для retry. Значения по умолчанию: `[408, 429, 500, 502, 503, 504]`.

### RetryHandlerInterface
Контракт обработчика retry. Управляет повторами по RetryConfig и shouldRetry(), повторяет только транспортный участок pipeline.

### withRetry(int $attempts)
Метод AbstractRequest. Включает или переопределяет retry в рантайме.

### withoutRetry()
Метод AbstractRequest. Отключает retry для конкретного вызова.

## Rate Limiting

### RateLimit (атрибут)
Атрибут конфигурации rate‑limit на уровне запроса. Параметры: limit, period. Для endpoint со своими лимитами.

### RateLimitBehavior
Namespace: `Brahmic\ApiSutra\Enums\RateLimiting\RateLimitBehavior`.
Enum поведения при достижении лимита. Wait — ждать освобождения слота (default). Throw — сразу ошибка.

### withRateLimit(int $limit, int $period)
Метод AbstractRequest. Устанавливает rate‑limit в рантайме.

### withoutRateLimit()
Метод AbstractRequest. Отключает throttling для конкретного вызова.

### RateLimiter
Внутренний компонент для подсчёта запросов. По умолчанию in-memory, опционально внешний store через PSR-16.

## Конфигурация выполнения

### ExecutionMode
Namespace: `Brahmic\ApiSutra\Enums\Execution\ExecutionMode`.
Enum режима выполнения вложенных и зависимых запросов. Sequential — последовательное выполнение. Parallel — параллельное выполнение.

### FailStrategy
Namespace: `Brahmic\ApiSutra\Enums\Execution\FailStrategy`.
Enum стратегии обработки ошибок. FailAll — любая ошибка приводит к провалу всего запроса. Partial — успешные результаты возвращаются вместе с ошибками. IgnoreErrors — ошибки молча игнорируются.

### Execution (атрибут)
Атрибут конфигурации выполнения вложенных запросов. Принимает ExecutionMode и FailStrategy. Применяется к CompositeRequestInterface. Для DependsOnRequestInterface режим всегда Sequential, учитывается только failStrategy. Для простых запросов игнорируется.

## Idempotency

### Idempotent (атрибут)
Атрибут для мутирующих запросов. SDK автоматически генерирует и добавляет Idempotency-Key header. Защита от дубликатов при retry. Параметр header для кастомного имени заголовка.

### withIdempotencyKey()
Метод AbstractRequest. Устанавливает кастомный idempotency key вместо автогенерации.

### idempotencyHeader
Параметр ClientConfig. Имя заголовка для idempotency key. По умолчанию 'Idempotency-Key'.
