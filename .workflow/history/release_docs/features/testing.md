# Тестирование

## Обзор

SDK предоставляет инструменты для тестирования без реальных HTTP-запросов.

**Возможности:**
- MockClient — подмена транспорта для тестов
- Fixture recording — запись реальных ответов
- Детерминированные тесты без внешних зависимостей
- Redacting — скрытие чувствительных данных в fixtures

## Принципы тестирования (база)
- Пирамида: unit → integration → e2e.
- Мокаем только интерфейсы, не VO/DTO.
- Идемпотентность: ключевые трансформы/сериализация должны быть детерминированны.
- Fixtures для сложных структур, factories для динамики.
- Именование: `test_method_scenario_result`, структура AAA (Arrange/Act/Assert).

---

## Архитектура тестирования

### Transport Layer

Тестирование реализовано через абстракцию транспортного слоя:

```
┌─────────────────────────────────────────────────────────┐
│                    Container                            │
│  ┌───────────────────┐      ┌────────────────────────┐  │
│  │ TransportInterface│─────▶│ HttpTransport (prod)  │  │
│  │                   │      │ MockTransport (test)   │  │
│  └───────────────────┘      └────────────────────────┘  │
│           │                                             │
│           ▼                                             │
│  ┌─────────────────┐                                    │
│  │ AbstractClient  │◀── resolve                         │
│  └─────────────────┘                                    │
└─────────────────────────────────────────────────────────┘
```

**Компоненты:**

```php
interface TransportInterface
{
    public function send(PreparedRequest $request): ProviderResponse;
}

// Production — реальные HTTP-запросы
readonly class HttpTransport implements TransportInterface { }

// Testing — mock-ответы
class MockTransport implements TransportInterface { }
```

**Преимущества:**
- Нет static state в core классах
- Параллельные тесты изолированы
- Чистый DI, нет "магии"

### Global MockClient

Для тестирования запросов, созданных напрямую `(new GetUser(1))->send()`:

```php
// Устанавливает MockTransport в контейнер
MockClient::global([
    GetUser::class => ['id' => 1, 'name' => 'John'],
]);

// Любой запрос перехватывается
(new GetUser(1))->send();              // ✅ Mock
$client->users()->get(1)->send();      // ✅ Mock
```

**Очистка между тестами:**

```php
// В setUp/tearDown
MockClient::destroyGlobal();
```

---

## MockClient (Fake)

Подменяет реальный HTTP-транспорт заготовленными ответами.

### Базовое использование

```php
$client = new ApiClient(config: $config);

// Подменить ответы
$client->fake([
    GetUser::class => ['id' => 1, 'name' => 'John'],
    GetOrder::class => ['id' => 123, 'status' => 'completed'],
]);

// Запросы возвращают mock-данные
$result = $client->users()->get(1)->send();
// $result->data->name === 'John'
```

### Формат ответов

```php
$client->fake([
    // Массив данных → успешный ответ
    GetUser::class => ['name' => 'John'],
    
    // MockResponse для контроля статуса и заголовков
    GetOrder::class => MockResponse::make(
        data: ['id' => 123],
        status: 200,
        headers: ['X-Request-Id' => 'abc'],
    ),
    
    // Ошибка
    CreatePayment::class => MockResponse::make(
        data: ['error' => 'Insufficient funds'],
        status: 400,
    ),
    
    // Closure для динамических mock
    GetProfile::class => fn(AbstractRequest $r) => MockResponse::make(
        data: ['id' => $r->userId, 'name' => "User {$r->userId}"],
    ),
    
    // URL-pattern (wildcard)
    'api.example.com/users/*' => MockResponse::make(['name' => 'John']),
    'api.example.com/orders/*' => MockResponse::make(status: 404),
    
    // Wildcard — для всех остальных
    '*' => MockResponse::make(status: 404),
]);
```

### MockResponse

```php
readonly class MockResponse
{
    public static function make(
        array|string $data = [],
        int $status = 200,
        array $headers = [],
    ): self;
    
    // Helpers
    public static function success(array $data): self;
    public static function notFound(): self;
    public static function serverError(): self;
    public static function rateLimited(int $retryAfter = 60): self;
}
```

### Последовательные ответы

Для тестирования retry или пагинации:

```php
$client->fake([
    GetOrder::class => MockResponse::sequence([
        MockResponse::serverError(),      // первый вызов — 500
        MockResponse::success(['id' => 1]), // retry — успех
    ]),
]);
```

### Проверка цепочки `withCache()->withPage()`

Цель: убедиться, что опции кеша не теряются при добавлении пагинации.

```php
use Brahmic\ApiSutra\Enums\Cache\CacheMode;

$request = new ListOrders();
$execution = $request
    ->withCache(300)
    ->withPage(2)
    ->withLimit(10);

$options = $execution->getOptions();
$pagination = $execution->getPaginationOptions();

// Проверка runtime‑опций
expect($options->getCacheOverride()->mode)->toBe(CacheMode::Enabled);
expect($options->getCacheOverride()->ttl)->toBe(300);
expect($pagination->getPage())->toBe(2);
expect($pagination->getLimit())->toBe(10);
```

Опционально: прогнать через pipeline с `MockTransport` и проверить, что
query содержит `page/limit`, а кеш‑ключ учитывает эти параметры.

### Проверка вызовов

```php
$client->fake([...]);

$client->users()->get(1)->send();
$client->users()->get(2)->send();

// Assertions
$client->assertSent(GetUser::class);
$client->assertSent(GetUser::class, times: 2);
$client->assertNotSent(CreateUser::class);
$client->assertNothingSent();  // ничего не отправлено

// С проверкой параметров
$client->assertSent(GetUser::class, function ($request) {
    return $request->userId === 1;
});
```

### Предотвращение реальных запросов

```php
// В тестах — убедиться что все запросы замоканы
$client->preventStrayRequests();

// Незамоканный запрос выбросит исключение
$client->orders()->list()->send(); // throws UnmockedRequestException
```

### Запрет записи fixtures (для CI)

```php
// В CI — запретить запись новых fixtures
MockConfig::throwOnMissingFixtures();

// При попытке записи — исключение
$client->playback('/fixtures');
$client->users()->get(999)->send(); // throws MissingFixtureException
```

---

## Fixture Recording

Запись реальных ответов API для использования в тестах.

### Запись

```php
// Включить запись
$client->record('/path/to/fixtures');

// Выполнить реальные запросы
$client->users()->get(1)->send();
$client->orders()->list()->send();

// Fixtures сохраняются в файлы:
// /path/to/fixtures/GetUser_1.json
// /path/to/fixtures/ListOrders.json
```

### Воспроизведение

```php
// В тестах — воспроизвести записанные ответы
$client->playback('/path/to/fixtures');

// Запросы используют сохранённые fixtures
$result = $client->users()->get(1)->send();
```

### Формат fixture

```json
{
  "request": {
    "class": "App\\Requests\\GetUser",
    "method": "GET",
    "url": "https://api.example.com/users/1",
    "headers": {},
    "body": null
  },
  "response": {
    "status": 200,
    "headers": {
      "Content-Type": "application/json"
    },
    "body": {
      "id": 1,
      "name": "John"
    }
  },
  "recorded_at": "2025-01-07T12:00:00Z"
}
```

### Redacting fixtures

Скрытие чувствительных данных в записанных fixtures:

```php
use Sdk\Testing\Fixture;

class UserFixture extends Fixture
{
    protected function defineName(): string
    {
        return 'users/get-user';
    }
    
    // Скрыть заголовки
    protected function defineSensitiveHeaders(): array
    {
        return [
            'Authorization' => 'REDACTED',
            'X-Api-Key' => 'REDACTED',
        ];
    }
    
    // Скрыть JSON-поля
    protected function defineSensitiveJsonParameters(): array
    {
        return [
            'email' => 'REDACTED',
            'phone' => fn() => fake()->phoneNumber(),  // closure для faker
            'password' => 'REDACTED',
        ];
    }
    
    // Regex-замены для других форматов
    protected function defineSensitiveRegexPatterns(): array
    {
        return [
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/' => 'email@redacted.com',
        ];
    }
}
```

**Использование:**

```php
$client->fake([
    GetUser::class => new UserFixture(),
]);
```

---

## Request Pool

Массовое выполнение запросов с контролем concurrency.

### Создание pool

```php
// Generator для lazy loading
$requests = function () use ($userIds) {
    foreach ($userIds as $id) {
        yield new GetUser($id);
    }
};

$pool = $client->pool(
    requests: $requests,
    concurrency: 10,  // максимум 10 одновременно
);
```

Concurrency вычисляется один раз при старте выполнения. Динамический пересчёт в процессе не выполняется.

В `pool()` допускаются только `RequestInterface` элементы; неподдерживаемые значения приводят к `ConfigurationException`.

### Handlers

```php
$pool = $pool
    ->withResponseHandler(function (ExecutionResult $result, AbstractRequest $request) {
        // Обработка успешного ответа
        Log::info("Loaded user {$result->data->id}");
    })
    ->withExceptionHandler(function (Throwable $exception, AbstractRequest $request) {
        // Обработка ошибки
        Log::error("Failed to load user: {$exception->getMessage()}");
    });
```

### Выполнение

```php
// Асинхронное выполнение
$pool->send()->wait();

// Или с получением всех результатов
$results = $pool->send()->results();
```

### PoolConfig

```php
$pool = $client->pool(
    requests: $requests,
    config: new PoolConfig(
        concurrency: 10,
        stopOnFailure: false,  // продолжать при ошибках
    ),
);
```

### Отличие от Batch

| Аспект | Batch | Pool |
|--------|-------|------|
| Количество | Небольшое (2-10) | Большое (100+) |
| Типы запросов | Разные | Обычно однотипные |
| Определение | Массив | Generator (lazy) |
| Concurrency | Все сразу или sequential | Контролируемый лимит |
| Use case | "user + orders + settings" | "Импорт 5000 записей" |

---

## Async операции

### sendAsync()

Одиночный async запрос:

```php
$promise = $client->users()->get(1)->sendAsync();

$promise
    ->then(fn(ExecutionResult $result) => process($result->data))
    ->otherwise(fn(SdkException $e) => log($e));

// Fire-and-forget (не ждём)
$client->webhooks()->notify($event)->sendAsync();

// Явное ожидание
$result = $promise->wait();
```

### Batch async

```php
$promise = $client->batch($requests)->sendAsync();

$promise->then(fn(BatchResult $result) => {
    foreach ($result->results() as $r) {
        // обработка
    }
});

$result = $promise->wait();
```

### Параллельные запросы с разной обработкой

```php
$userPromise = $client->users()->get(1)->sendAsync();
$ordersPromise = $client->orders()->list()->sendAsync();

$userPromise->then(fn($r) => $this->user = $r->data);
$ordersPromise->then(fn($r) => $this->orders = $r->data);

// Ждём оба
Promise\Utils::all([$userPromise, $ordersPromise])->wait();
```

`sendAsync()` всегда возвращает `ExecutionResult` (resolve), а ошибки
появляются только при runtime‑сбоях транспорта или сериализации.

### Сводная таблица методов выполнения

| Метод | Тип | Возвращает |
|-------|-----|------------|
| `send()` | sync | `ExecutionResult` |
| `sendAsync()` | async | `PromiseInterface<ExecutionResult>` |
| `batch()->send()` | sync | `BatchResult` |
| `batch()->sendAsync()` | async | `PromiseInterface<BatchResult>` |
| `pool()->send()` | async | `PoolResult` |

---

## Интеграция с PHPUnit

```php
class OrderApiTest extends TestCase
{
    private ApiClient $client;
    
    protected function setUp(): void
    {
        $this->client = new ApiClient(config: $this->config);
        $this->client->preventStrayRequests();
    }
    
    public function test_get_order_returns_dto(): void
    {
        $this->client->fake([
            GetOrder::class => ['id' => '123', 'status' => 'completed'],
        ]);
        
        $result = $this->client->orders()->get('123')->send();
        
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('123', $result->data->id);
        $this->assertEquals('completed', $result->data->status);
        
        $this->client->assertSent(GetOrder::class);
    }
    
    public function test_handles_server_error(): void
    {
        $this->client->fake([
            GetOrder::class => MockResponse::serverError(),
        ]);
        
        $result = $this->client->orders()->get('123')->send();
        
        $this->assertTrue($result->isFailed());
        $this->assertEquals(ErrorCode::ServerError, $result->errors->first()->code);
    }
}
```

---

## Интеграция с Pest

```php
beforeEach(function () {
    $this->client = new ApiClient(config: testConfig());
    $this->client->preventStrayRequests();
});

it('returns order dto', function () {
    $this->client->fake([
        GetOrder::class => ['id' => '123', 'status' => 'completed'],
    ]);
    
    $result = $this->client->orders()->get('123')->send();
    
    expect($result)
        ->isSuccess()->toBeTrue()
        ->data->id->toBe('123')
        ->data->status->toBe('completed');
});

it('handles validation errors', function () {
    $result = $this->client->users()->create(
        email: 'invalid-email',
    )->send();
    
    expect($result)
        ->isFailed()->toBeTrue()
        ->hasValidationErrors()->toBeTrue()
        ->validationErrors->toHaveCount(1);
});
```

---

## Резюме

| Инструмент | Назначение |
|------------|------------|
| `TransportInterface` | Абстракция HTTP-транспорта |
| `MockTransport` | Mock-реализация для тестов |
| `MockClient::global()` | Глобальный mock через DI |
| `fake()` | Подмена ответов на клиенте |
| `MockResponse` | Контроль статуса, заголовков, данных |
| Closure mock | Динамические mock по параметрам запроса |
| URL-pattern | Mock по URL с wildcard |
| `assertSent()` | Проверка вызовов |
| `assertNothingSent()` | Проверка отсутствия вызовов |
| `preventStrayRequests()` | Защита от незамоканных запросов |
| `MockConfig::throwOnMissingFixtures()` | Запрет записи fixtures (CI) |
| `record()` / `playback()` | Запись и воспроизведение fixtures |
| Redacting | Скрытие чувствительных данных |
| `sendAsync()` | Async запрос с Promise |
| `batch()->sendAsync()` | Async batch с Promise |
| `pool()` | Массовые операции с concurrency |
