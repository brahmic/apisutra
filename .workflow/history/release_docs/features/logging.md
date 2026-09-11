# Логирование и Audit Log

## Обзор

SDK предоставляет два механизма отслеживания:
- **PSR-3 логирование** — интеграция с любым логгером
- **Audit log** — структурированная история выполнения pipeline

## TraceId

Уникальный идентификатор, связывающий все события одной операции.

```php
// SDK генерирует автоматически
$result = $client->orders()->get(123)->send();
$result->traceId; // "550e8400-e29b-41d4-a716-446655440000"
```

### Способы установки

```php
// Вариант A: на уровне запроса (точечно)
$request->withTraceId($externalId)->send();

// Вариант B: на уровне клиента (все последующие запросы)
$client->setTraceId($externalId);
$client->orders()->get(1)->send();  // использует externalId
$client->orders()->get(2)->send();  // использует externalId
```

**Приоритет:** запрос → клиент → автогенерация

### Интеграция с приложением

SDK — это HTTP-клиент, он отправляет запросы наружу, а не принимает входящие. Доступа к входящему HTTP-запросу приложения у него нет.

Интеграция происходит на стороне приложения:

```php
// Это код ПРИЛОЖЕНИЯ (например, Laravel middleware)
public function handle($request, $next)  // $request — входящий HTTP в приложение
{
    // Приложение извлекает traceId из своего входящего запроса
    $traceId = $request->header('X-Trace-Id') ?? Str::uuid();
    
    // И передаёт в SDK-клиент
    $this->apiClient->setTraceId($traceId);
    
    return $next($request);
}
```

**Цепочка:**
```
Внешняя система → [X-Trace-Id] → Приложение → setTraceId() → SDK → логи/audit
```

Все записи в логах и audit содержат этот ID — можно отфильтровать весь путь запроса.

## PSR-3 Логирование

### Конфигурация

```php
use Psr\Log\LoggerInterface;

$client = new Client(
    config: new ClientConfig(
        logger: $psrLogger,        // PSR-3 совместимый (Monolog, Laravel Log, etc.)
        logLevel: LogLevel::Debug, // Минимальный уровень записи
    )
);
```

По умолчанию — `NullLogger` (логирование отключено).

### Уровни логирования

| Уровень | Что записывается |
|---------|------------------|
| ERROR   | Ошибки, exceptions |
| WARNING | Partial failures, retries |
| INFO    | Start/end запроса, статус |
| DEBUG   | PreparedRequest, ProviderResponse, hooks |

### Формат записей

```
[2024-01-15 10:30:15] sdk.INFO: Request started {"trace":"abc-123","request":"GetOrder"}
[2024-01-15 10:30:16] sdk.DEBUG: HTTP request sent {"trace":"abc-123","method":"GET","url":"..."}
[2024-01-15 10:30:16] sdk.INFO: Request completed {"trace":"abc-123","status":"success","duration":1.23}
```

## Audit Log

Структурированная коллекция событий для программного анализа.

### Получение

```php
$result = $client->orders()->get(123)->send();

foreach ($result->audit as $event) {
    echo "{$event->stage->value}: {$event->duration}ms\n";
}
```

### PipelineEvent

Namespace: `PipelineStage` — `Brahmic\ApiSutra\Enums\Pipeline\PipelineStage`, `RequestRole` — `Brahmic\ApiSutra\Enums\Execution\RequestRole`.

```php
readonly class PipelineEvent
{
    public function __construct(
        public PipelineStage $stage,    // Этап pipeline
        public float $timestamp,         // microtime начала
        public ?float $duration,         // Длительность (мс)
        public ?string $requestClass,    // Класс запроса
        public RequestRole $role,        // root/nested/dependency
        public mixed $payload,           // Детали (только в debug mode)
    ) {}
}
```

### PipelineStage

Namespace: `Brahmic\ApiSutra\Enums\Pipeline\PipelineStage`.

```php
enum PipelineStage: string
{
    case Started = 'started';           // Начало выполнения
    case BeforeSend = 'before_send';    // До отправки HTTP
    case HttpRequest = 'http_request';  // Отправка запроса
    case HttpResponse = 'http_response'; // Получение ответа
    case BeforeHydrate = 'before_hydrate'; // До создания DTO
    case AfterHydrate = 'after_hydrate';   // После создания DTO
    case Completed = 'completed';       // Успешное завершение
    case Failed = 'failed';             // Ошибка
}
```

### Поведение payload

- **Обычный режим:** `payload` = `null` (минимальные накладные расходы)
- **Debug mode:** `payload` содержит данные этапа (PreparedRequest, ProviderResponse, etc.)

### Пример audit для Composite запроса

```php
$result = $client->orders()->getBulk([1, 2, 3])->send();

// audit содержит события всех вложенных запросов:
// [0] Started (root, GetBulkOrders)
// [1] Started (nested, GetOrder #1)
// [2] HttpRequest (nested, GetOrder #1)
// [3] HttpResponse (nested, GetOrder #1)
// [4] Completed (nested, GetOrder #1)
// [5] Started (nested, GetOrder #2)
// ... и т.д.
// [N] Completed (root, GetBulkOrders)
```

## Интеграция с внешними системами

### Distributed Tracing (OpenTelemetry, Jaeger)

Базовая интеграция через traceId:

```php
// Получить trace ID из внешней системы
$externalTraceId = $span->getContext()->getTraceId();

// Передать в SDK
$result = $request->withTraceId($externalTraceId)->send();
```

Полная интеграция (spans, attributes) — через middleware/hooks в будущих версиях.

## Резюме

| Механизм | Когда использовать |
|----------|-------------------|
| PSR-3 Log | Централизованное логирование, мониторинг |
| Audit Log | Отладка, профилирование, анализ pipeline |
| TraceId | Связывание событий, distributed tracing |
