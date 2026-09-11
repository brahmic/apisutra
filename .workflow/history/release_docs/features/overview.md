# API Core SDK — Обзор возможностей

Универсальное ядро для создания API клиентов с декларативным подходом.

## Философия

**"Разработчик описывает — ядро делает"**

Минимум boilerplate, максимум декларативности. Разработчик SDK описывает структуру API через атрибуты и классы, ядро берёт на себя:
- HTTP транспорт
- Сериализацию/десериализацию
- Обработку ошибок
- Retry и Rate limiting

## Ключевые возможности

### 1. Декларативные запросы

Запрос — это класс с атрибутами, наследуемый от `AbstractRequest`. Никакой логики внутри.

```php
#[Get('/orders/{orderId}')]
#[Returns(OrderResponse::class)]
readonly class GetOrder extends AbstractRequest
{
    public function __construct(
        public string $orderId,  // автоматом → {orderId} в URL
    ) {}
}
```

Свойства автоматически маппятся в path/query/body по convention. Явное управление через атрибуты `#[Path]`, `#[Query]`, `#[Body]`, `#[Header]`, `#[Ignore]`. Валидация через `#[Validate]`.

См. [Сериализация запросов](./request-serialization.md), [NamingStrategy](./naming-strategy.md)

### 2. Fluent навигация через Resources

Паттерн `Client → Resource → Resource → Request`:

```php
$client->billing()->orders()->get($id);
```

Каждый уровень:
- Возвращает следующий Resource (навигация)
- Или возвращает Request (конечная точка)

### 3. Типизированные DTO

Автоматическая гидрация ответов в типизированные объекты:

```php
readonly class OrderResponse extends AbstractResponseDto
{
    public function __construct(
        public string $id,
        public CustomerDto $customer,      // автогидрация по типу
        #[Nested(type: ItemDto::class)]
        public array $items,               // массив DTO
    ) {}
}
```

См. [DTO и Гидрация](./dto-hydration.md)

### 4. Pluggable аутентификация

Стратегии аутентификации через интерфейс:

```php
$client = new Client(config: new ClientConfig(
    auth: new TokenAuthenticator(
        username: 'user',
        password: 'pass',
    ),
));

// Публичный endpoint без auth
#[NoAuth]
class GetPublicStatus extends AbstractRequest { }
```

- Автоматический refresh токена
- Хранение в кеше
- Retry при 401
- Готовые реализации: Bearer, API Key, Basic, HMAC

См. [Аутентификация](./authentication.md)

### 5. Логирование и Audit

Сквозное отслеживание выполнения запросов:

```php
$result = $client->orders()->get($id);

$result->traceId;  // UUID для связи всех событий
$result->audit;    // Структурированная история pipeline
```

- PSR-3 совместимый логгер
- TraceId для distributed tracing
- Audit log для программного анализа

См. [Логирование и Audit Log](./logging.md)

### 6. Кеширование

Каскадное кеширование с переопределением в рантайме:

```php
// Уровень клиента (defaults)
$client = new Client(config: new ClientConfig(
    cache: new CacheConfig(
        store: $psrCache,
        ttl: 3600,
    ),
));

// Уровень запроса (атрибут)
#[Cache(ttl: 300)]
class GetOrder extends AbstractRequest { }

// Рантайм
$request->withoutCache()->send();      // свежие данные без кеша
$request->withCacheWriteOnly()->send(); // свежие данные + обновить кеш
$request->withCache(ttl: 60)->send();  // переопределить TTL
```

См. [Кеширование](./caching.md)

### 7. Retry, Rate Limiting и Delay

Устойчивость к сбоям и соблюдение лимитов API:

```php
$client = new Client(config: new ClientConfig(
    delay: 100,  // мс между запросами
    retry: new RetryConfig(
        attempts: 3,
        backoff: BackoffStrategy::Exponential,
        jitter: true,
    ),
    rateLimit: new RateLimitConfig(
        limit: 100,
        period: 60,
    ),
));

// Переопределение в рантайме
$request->withoutRetry()->send();
$request->withDelay(500)->send();
$request->withRateLimit(limit: 10, period: 60)->send();
```

- Delay — фиксированная пауза между запросами
- Exponential backoff с jitter
- Idempotency keys для защиты от дубликатов
- Кастомная логика retry через `shouldRetry()`

См. [Retry и Rate Limiting](./retry-rate-limiting.md)

### 8. Result Objects и обработка ошибок

Типизированные результаты вместо исключений для бизнес-ошибок:

```php
$result = $client->orders()->get($id)->send();

// Статус (enum: SUCCESS | PARTIAL | FAILED)
$result->status;

// Проверки состояния
$result->isSuccess();   // Полный успех
$result->isPartial();   // Частичный успех (для composite)
$result->isFailed();    // Полный провал

// Практичные проверки
$result->hasData();     // Есть данные (можно работать)
$result->hasErrors();   // Есть ошибки

// Данные
$result->data;          // DTO или partial data
$result->errors;        // ErrorCollection
```

**Опционально — throw() для fail-fast:**

```php
// Выбросит exception если isFailed()
$order = $client->orders()->get($id)->send()->throw()->data;
```

**Глобальный режим исключений:**

```php
$client = new Client(config: new ClientConfig(
    throwOnErrors: true,  // автоматический throw при ошибках
));
```

**Кастомная логика определения failed:**

```php
class MyClient extends AbstractClient
{
    // Провайдер возвращает 200, ошибки в body
    protected function hasRequestFailed(ProviderResponse $response): bool
    {
        return $response->json('success') === false;
    }
}
```

См. [Исключения](./exceptions.md)

### 9. Laravel интеграция

Автоматический DI запросов в контроллеры:

```php
class OrderController extends Controller
{
    public function show(GetOrder $request): JsonResponse
    {
        // $request заполнен из HTTP request автоматически
        return response()->json($request->send()->data);
    }
}
```

См. [Laravel интеграция](./laravel-integration.md)

### 10. Централизованные хуки

Регистрация обработчиков для групп запросов или DTO:

```php
$hooks = new HookRegistry(resolver: fn($class) => app($class));

$hooks->on(Hook::BeforeSend, AddTraceHeader::class);  // глобально
$hooks->on(Hook::BeforeHydrate, UnwrapData::class, forDto: ApiWrapper::class);  // для DTO
```

См. [Хуки и HookRegistry](./hooks.md)

### 11. Кастомные атрибуты

Регистрация обработчиков для собственных атрибутов:

```php
$attributes = new AttributeRegistry(resolver: fn($class) => app($class));
$attributes->register(LogSensitive::class, LogSensitiveHandler::class);
```

SDK автоматически вызывает обработчик при обнаружении атрибута.

См. [Механизм атрибутов](./attributes.md)

### 12. Batch и Composite

**Composite** — агрегация запросов в один DTO (compile-time):

```php
$result = $client->person()->fullData($uuid)->send();
// Один PersonFullData DTO
```

**Batch** — коллекция результатов (runtime):

```php
$result = $client->batch($requests)->send();
// BatchResult (extends ExecutionResult) с вложенными результатами
```

См. [Request Pipeline](./request-pipeline.md) и [Batch (Runtime)](./batch.md)

### 12a. Асинхронные операции

**sendAsync()** — одиночный async запрос с Promise:

```php
$promise = $client->users()->get(1)->sendAsync();

$promise
    ->then(fn(ExecutionResult $r) => process($r->data))
    ->otherwise(fn(SdkException $e) => log($e));

// Fire-and-forget
$client->webhooks()->notify($event)->sendAsync();
```

`sendAsync()` всегда resolve'ит `ExecutionResult`. Исключения выбрасываются
только при runtime‑сбоях (сломанный transport/serialization), а не при failed‑результате.

**Batch async:**

```php
$promise = $client->batch($requests)->sendAsync();
$promise->then(fn(BatchResult $r) => ...);
```

| Метод | Тип | Возвращает |
|-------|-----|------------|
| `send()` | sync | `ExecutionResult` |
| `sendAsync()` | async | `PromiseInterface<ExecutionResult>` |
| `batch()->send()` | sync | `BatchResult` |
| `batch()->sendAsync()` | async | `PromiseInterface<BatchResult>` |
| `pool()` | async | `PoolResult` |

### 13. Тестирование

MockClient для тестов без реальных HTTP-запросов:

```php
$client->fake([
    GetUser::class => ['id' => 1, 'name' => 'John'],
    '*' => MockResponse::notFound(),
]);

$result = $client->users()->get(1)->send();
// → mock-данные, без HTTP

$client->assertSent(GetUser::class);
```

**Request Pool** — массовые операции с контролем concurrency (фиксируется на старт выполнения):

```php
$pool = $client->pool($requests, concurrency: 10);
$pool->send()->wait();
```

В `pool()` допускаются только `RequestInterface` элементы; неподдерживаемые значения приводят к `ConfigurationException`.

См. [Тестирование](./testing.md)

### 14. Валидация запросов

Валидация свойств перед отправкой (Laravel rules):

```php
#[Post('/check')]
class CheckPerson extends AbstractRequest
{
    #[Label('Фамилия')]
    #[Validate('required|regex:/^[а-яёА-ЯЁ\- ]+/')]
    public string $lastName;
}
```

При ошибке — запрос не отправляется, возвращается `ValidationError`.

См. [Валидация](./validation.md)

### 15. Касты (Type Casting)

Автоматическое преобразование типов при гидрации и сериализации:

```php
// Автокаст по типу свойства
public Carbon $createdAt;

// Явный каст с параметрами
#[Cast(DateTimeCast::class, format: 'd.m.Y')]
public ?Carbon $birthDate;

// Глобальная регистрация
$config = new ClientConfig(
    casts: [Money::class => MoneyCast::class],
);
```

См. [Касты](./casts.md)

### 16. Extensions (Расширения)

Модульная система для дополнительного функционала:

```php
$client = new MyClient(
    config: new ClientConfig(
        extensions: [
            new ArchiveExtension(),  // built-in: работа с архивами
            new MyCustomExtension(), // кастомное расширение
        ],
    ),
);

// Работа с архивами
$response = $client->files()->download($id)->send();

if ($response->data->isArchive()) {
    $archive = $response->data->asArchive();
    $file = $archive->extract('report.pdf');
    $file->saveTo('/storage/reports/');
}
```

**Lifecycle:**
- `register()` — декларация компонентов (сразу)
- `boot()` — инициализация (lazy, при первом использовании)

**Scope:** Extensions привязаны к конкретному Client, не глобально.

См. [Extensions](./extensions.md)

---

## Use Cases (в обсуждении)

> Этот раздел пополняется по мере обсуждения кейсов

### UC-001: Простой GET запрос

**Описание:** Получить данные по ID

**Пример:**
```php
$order = $client->orders()->get('xxx-yyy-zzz');
```

**Что делает ядро:**
1. Формирует URL: `{baseUrl}/orders/xxx-yyy-zzz`
2. Добавляет аутентификацию
3. Отправляет GET запрос
4. Десериализует ответ в `OrderResponse`
5. Возвращает `Result<OrderResponse, ApiError>`

---

### UC-002: POST запрос с телом

**Описание:** Создать новую сущность

**Пример:**
```php
$result = $client->orders()->create(
    new CreateOrderRequest(
        type: 'standard',
        items: [...],
    )
);
```

---

### UC-003: Композитный запрос

**Описание:** Получить данные из нескольких источников одним вызовом

**Пример:**
```php
$result = $client->person()->fullData($uuid)->send();
// Внутри выполняются: GetBankruptcy, GetCourts, GetFssp
// Результаты агрегируются в PersonFullData DTO
```

См. [Request Pipeline](./request-pipeline.md#композитные-запросы-composite)

---

### UC-003a: Runtime Batch

**Описание:** Выполнить динамическую коллекцию запросов

**Пример:**
```php
$requests = RequestCollection::make([
    new GetOrder($id1),
    new GetOrder($id2),
    new GetInvoice($invoiceId),
]);

$result = $client->batch($requests)->send();
// BatchResult с вложенными ExecutionResult
```

См. [Batch (Runtime)](./batch.md)

---

### UC-004: Запрос с зависимостями

**Описание:** Запрос, которому нужны данные от других запросов перед выполнением

**Пример:**
```php
$result = $client->person()->report($lastName, $firstName)->send();
// Внутри: сначала GetUuid, GetToken, потом основной запрос
```

См. [Request Pipeline](./request-pipeline.md#запросы-с-зависимостями-dependson)

---

### UC-005: Запрос с query параметрами

**Описание:** GET запрос с параметрами фильтрации

**Пример:**
```php
$result = $client->orders()->list(page: 1, limit: 10, status: 'active');
```

См. [Сериализация запросов](./request-serialization.md)

---

### UC-006: Пагинация

**Описание:** Работа с пагинированными API — одиночные запросы и массовая загрузка

**Пример:**
```php
// Одна страница
$result = $client->orders()->list(page: 2)->send();

// Все данные
$all = $client->orders()->list()->paginate()->all();

// Итерация
foreach ($client->orders()->list()->paginate() as $page) {
    process($page->data);
}
```

См. [Пагинация](./pagination.md)

---

### UC-007: Работа с файлами

**Описание:** Upload и download файлов

**Upload:**
```php
$result = $client->documents()->upload(
    title: 'Договор',
    file: FileInput::fromPath('/tmp/contract.pdf'),
)->send();
```

**Download:**
```php
// Сохранить на диск
$client->reports()->download($id)->saveTo('/tmp/report.pdf')->send();

// Или получить stream
$result = $client->reports()->download($id)->send();
$stream = $result->data->stream();
```

**Base64 в response:**
```php
readonly class DocumentResponse extends AbstractResponseDto
{
    public Base64File $content;  // по типу
    
    #[Nested(type: Base64File::class)]
    public array $attachments;  // array<Base64File>
}
```

См. [Работа с файлами](./files.md)

---

### UC-008: Long-running операции (polling)

**Описание:** Асинхронные операции где результат не возвращается сразу (генерация отчётов, обработка данных)

**Подход:** SDK остаётся stateless. Polling — это orchestration layer, ответственность приложения.

**SDK предоставляет:**
- Обычные запросы (создание задачи, проверка статуса, получение результата)
- DTO с enum-статусом и optional result — структура зависит от провайдера
- Консистентный ExecutionResult

**Приложение управляет:**
- Хранением taskId
- Интервалами и таймаутами опроса
- Бизнес-логикой retry

Специальных механизмов в SDK не требуется — используются существующие примитивы.

---

## Отложенные задачи

| Задача | Описание | Приоритет |
|--------|----------|-----------|
| Circuit Breaker | Паттерн защиты от каскадных сбоев | Medium |
| Transport Middleware | Transport как middleware stack (logging, retry, cache на уровне транспорта) | Low |
| Chunked Upload | Загрузка больших файлов частями (resumable) | Low |
| Progress Tracking | Callback для отслеживания прогресса upload/download | Low |

---

## Принципы

| Принцип | Реализация |
|---------|------------|
| **Stateless** | Каждый вызов независим |
| **Immutable** | Все DTO — readonly |
| **Декларативность** | Атрибуты вместо логики |
| **Loose Coupling** | Ядро не знает о конкретных API |
| **Convention over Config** | Разумные defaults |
| **Консистентность** | PipelineContext доступен всегда, единый API для хуков |
| **Return-based** | Результаты через return, exceptions опционально |
| **DRY** | Один объект для выполнения и debug (PreparedRequest, ProviderResponse) |

---

## Документация по разделам

| Раздел | Описание |
|--------|----------|
| [DTO и Гидрация](./dto-hydration.md) | Автоматическая десериализация |
| [Сериализация запросов](./request-serialization.md) | Маппинг свойств в HTTP |
| [NamingStrategy](./naming-strategy.md) | Стратегии именования полей |
| [Request Pipeline](./request-pipeline.md) | Composite и DependsOn запросы |
| [Batch (Runtime)](./batch.md) | Динамические коллекции запросов |
| [Аутентификация](./authentication.md) | Стратегии auth, refresh токенов |
| [Кеширование](./caching.md) | Каскадное кеширование |
| [Retry и Rate Limiting](./retry-rate-limiting.md) | Устойчивость к сбоям |
| [Исключения](./exceptions.md) | Иерархия exceptions, throw() |
| [Хуки](./hooks.md) | Lifecycle hooks, HookRegistry |
| [Механизм атрибутов](./attributes.md) | Кастомные атрибуты |
| [Логирование](./logging.md) | TraceId, Audit log |
| [Валидация](./validation.md) | Валидация запросов |
| [Касты](./casts.md) | Type casting |
| [Файлы](./files.md) | Upload/download |
| [Пагинация](./pagination.md) | Работа со страницами |
| [Тестирование](./testing.md) | MockClient, Transport, fixtures |
| [Laravel интеграция](./laravel-integration.md) | DI, ServiceProvider |
