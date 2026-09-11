# Исключения и обработка ошибок

## Обзор

SDK использует **Result Object pattern** по умолчанию — исключения не выбрасываются автоматически.
Опционально доступен режим с исключениями через `throw()` или `throwOnErrors`.

---

## ResultStatus enum

```php
enum ResultStatus: string
{
    case SUCCESS = 'success';
    case PARTIAL = 'partial';
    case FAILED = 'failed';
}
```

---

## Result Object (по умолчанию)

```php
$result = $client->users()->get(1)->send();

if ($result->isFailed()) {
    $error = $result->errors->first();
    log($error->message);
    return null;
}

return $result->data;
```

**Преимущества:**
- Явный контроль
- Без try/catch
- Удобно для последовательной обработки

---

## throw() — опциональный fail-fast

```php
// Явный throw
$user = $client->users()->get(1)->send()->throw()->data;

// Цепочка с единой точкой обработки
try {
    $user = $client->users()->get(1)->send()->throw()->data;
    $orders = $client->orders()->forUser($user->id)->send()->throw()->data;
} catch (ApiException $e) {
    log($e->getMessage());
}
```

**throw()** выбрасывает исключение только если `isFailed() === true`.

---

## throwOnErrors — глобальный режим

```php
$client = new Client(
    config: new ClientConfig(
        throwOnErrors: true,  // автоматический throw при любой ошибке
    )
);

// Теперь все failed запросы выбрасывают исключения
$user = $client->users()->get(1)->send()->data;  // throws если failed
```

---

## Иерархия исключений

```
SdkException (базовый)
│
├── ControlFlowException (SDK обрабатывает внутри)
│   ├── RetryableException
│   │     - retryAfter: ?int (секунды)
│   │     - maxAttempts: ?int
│   ├── EarlyReturnException
│   │     - data: mixed (данные для возврата без HTTP)
│   └── RefreshTokenException (future)
│
├── ConnectionException (сеть, DNS, таймаут соединения)
│
├── ValidationException (локальная валидация DTO/Request)
│     - errors: array
│
├── RequestException (HTTP ошибки)
│   ├── ClientException (4xx)
│   │   ├── UnauthorizedException (401)
│   │   ├── PaymentRequiredException (402)
│   │   ├── ForbiddenException (403)
│   │   ├── NotFoundException (404)
│   │   ├── RequestTimeoutException (408)
│   │   ├── UnprocessableEntityException (422)
│   │   └── RateLimitException (429)
│   │         - retryAfter: ?int
│   └── ServerException (5xx)
│       ├── InternalServerException (500)
│       ├── BadGatewayException (502)
│       ├── ServiceUnavailableException (503)
│       └── GatewayTimeoutException (504)
│
├── ConfigurationException (ошибки конфигурации SDK)
│
├── ExtensionException (расширения)
│   ├── ExtensionConflictException (конфликт handler регистрации)
│   └── ExtensionDisabledException (использование disabled extension)
│
└── TestingException (тестирование)
    ├── UnmockedRequestException (запрос без mock)
    └── MissingFixtureException (fixture не найден)
```

### Два типа исключений

| Тип | Назначение | Обработка |
|-----|------------|-----------|
| **ControlFlowException** | Инструкция для SDK | SDK обрабатывает внутренне (retry, early return) |
| **Terminal** (остальные) | Финальная ошибка | Пользователь обрабатывает или Result Object |

### EarlyReturnException — прерывание без HTTP

Позволяет вернуть результат из hook без выполнения HTTP запроса:

```php
protected function beforeSend(PreparedRequest $request, PipelineContext $ctx): void
{
    $cacheKey = $this->getCacheKey($request);
    
    if ($cached = cache()->get($cacheKey)) {
        // Прерываем выполнение, возвращаем кешированные данные
        throw new EarlyReturnException(data: $cached);
    }
}
```

SDK перехватывает `EarlyReturnException` и строит `ExecutionResult` из переданных данных без HTTP вызова.

**Юзкейсы:**
- Кастомное кеширование в hook
- Короткое замыкание для тестов
- Возврат mock-данных по условию

---

## hasRequestFailed() — определение failed

Метод определяет, когда считать запрос неудачным. По умолчанию — HTTP status >= 400.

**Уровни (приоритет снизу вверх):**

```php
// SDK базовый (status >= 400)
    ↓
// Клиент — для всех запросов провайдера
class KonturClient extends AbstractClient
{
    protected function hasRequestFailed(ProviderResponse $response): bool
    {
        // Контур всегда 200, ошибки в body
        $body = $response->json();
        return isset($body['Error']) 
            || ($body['Success'] ?? true) === false;
    }
}
    ↓
// Запрос — для конкретного endpoint
class SpecialRequest extends AbstractRequest
{
    protected function hasRequestFailed(ProviderResponse $response): bool
    {
        return $response->json('status') === 'error';
    }
}
```

**Влияет на:** `$result->isFailed()`, `$result->isSuccess()`

---

## shouldRetry() — retry по содержимому

Определяет, нужен ли retry на основе body (не только HTTP кода).

```php
class KonturClient extends AbstractClient
{
    protected function shouldRetry(ProviderResponse $response, int $attempt): bool
    {
        $status = $response->json('status');
        
        return in_array($status, [
            'waiting_for_response',
            'source_unavailable',
        ]);
    }
}
```

**Приоритет retry:**
1. `shouldRetry()` — кастомная логика по body
2. `RetryableException` — из getRequestException()
3. `retryOn` — HTTP коды (429, 5xx)

---

## getRequestException() — кастомный тип исключения

Определяет, какое исключение выбросить при `throw()`.

```php
class KonturClient extends AbstractClient
{
    protected function getRequestException(ProviderResponse $response): Throwable
    {
        $error = $response->json('Error');
        $code = $error['Code'] ?? null;
        
        return match ($code) {
            'QUOTA_EXCEEDED' => new QuotaExceededException($error['Message']),
            'WAITING' => new RetryableException(
                message: 'Ожидание ответа',
                retryAfter: 5,
            ),
            'INVALID_INN' => new InvalidInnException($error['Message']),
            default => new KonturApiException($error['Message'] ?? 'Unknown error'),
        };
    }
}
```

**Использование:**
```php
try {
    $result = $client->check()->person($data)->send()->throw();
} catch (QuotaExceededException $e) {
    // Квота исчерпана — уведомить пользователя
} catch (RetryableException $e) {
    // SDK уже сделал retry, но не помогло
} catch (KonturApiException $e) {
    // Общая ошибка API
}
```

---

## Flow обработки ответа

```
Response получен
       ↓
┌─────────────────────────────────────┐
│ hasRequestFailed(response)          │
│ → определяет isFailed()             │
└─────────────────────────────────────┘
       ↓
┌─────────────────────────────────────┐
│ shouldRetry(response)?              │
│ ├── true → SDK делает retry         │
│ │         (если попытки остались)   │
│ └── false → продолжаем              │
└─────────────────────────────────────┘
       ↓
┌─────────────────────────────────────┐
│ throwOnErrors: true?                │
│ ├── true + isFailed:                │
│ │   → getRequestException() → throw │
│ └── false → return ExecutionResult   │
└─────────────────────────────────────┘
       ↓
Пользователь получает Result
       ↓
┌─────────────────────────────────────┐
│ result->throw() вызван?             │
│ ├── isFailed:                       │
│ │   → getRequestException() → throw │
│ └── isSuccess: return $this         │
└─────────────────────────────────────┘
```

---

## Примеры использования

### Базовый (Result Object)

```php
$result = $client->orders()->create($data)->send();

if ($result->isFailed()) {
    return response()->json([
        'error' => $result->errors->first()->message,
    ], 400);
}

return response()->json($result->data);
```

### С throw() для цепочки

```php
try {
    $user = $client->users()->get($id)->send()->throw()->data;
    $orders = $client->orders()->forUser($user->id)->send()->throw()->data;
    
    return response()->json(compact('user', 'orders'));
} catch (NotFoundException $e) {
    return response()->json(['error' => 'Not found'], 404);
} catch (SdkException $e) {
    return response()->json(['error' => $e->getMessage()], 500);
}
```

### Глобальный throwOnErrors

```php
// В ServiceProvider
$this->app->singleton(ApiClient::class, fn () => new ApiClient(
    config: new ClientConfig(
        baseUrl: config('api.url'),
        throwOnErrors: true,
    )
));

// В контроллере — исключения выбрасываются автоматически
public function show(int $id, ApiClient $client)
{
    $user = $client->users()->get($id)->send()->data;
    return response()->json($user);
}

// В Exception Handler — централизованная обработка
public function render($request, Throwable $e)
{
    if ($e instanceof NotFoundException) {
        return response()->json(['error' => 'Not found'], 404);
    }
    // ...
}
```

---

## Методы AbstractRequest

```php
// Доступны в запросе для переопределения
protected function hasRequestFailed(ProviderResponse $response): bool;
protected function shouldRetry(ProviderResponse $response, int $attempt): bool;
protected function getRequestException(ProviderResponse $response): Throwable;
```

## Методы AbstractClient

```php
// Доступны в клиенте для переопределения (defaults для всех запросов)
protected function hasRequestFailed(ProviderResponse $response): bool;
protected function shouldRetry(ProviderResponse $response, int $attempt): bool;
protected function getRequestException(ProviderResponse $response): Throwable;
```

## Методы ExecutionResult

```php
public function throw(): static;      // выбрасывает exception если isFailed()
public function isFailed(): bool;     // определено через hasRequestFailed()
public function isSuccess(): bool;    // !isFailed() && hasData()
public function errors: ErrorCollection;
```

---

## Testing Exceptions

Исключения для тестирования:

```php
// Выбрасывается при preventStrayRequests() если запрос не замокан
UnmockedRequestException

// Выбрасывается при throwOnMissingFixtures() если fixture не найден
MissingFixtureException
```

См. [Тестирование](./testing.md)

---

## Резюме

| Элемент | Назначение |
|---------|------------|
| `Result Object` | Основной паттерн, без исключений |
| `throw()` | Опциональный fail-fast |
| `throwOnErrors` | Глобальный режим исключений |
| `hasRequestFailed()` | Когда считать failed |
| `shouldRetry()` | Когда делать retry (по body) |
| `getRequestException()` | Какое исключение выбросить |
| `ControlFlowException` | Внутренние инструкции SDK |
| `TestingException` | Исключения тестирования |
| `SdkException` | Базовый класс всех исключений |
