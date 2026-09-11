# Core Classes

Базовые классы SDK.

---

## AbstractClient

Точка входа в SDK. Управляет конфигурацией, транспортом, регистрами.

```php
abstract class AbstractClient
{
    public function __construct(
        protected readonly ClientConfig $config,
        protected readonly TransportInterface $transport,
        protected readonly ?HookRegistry $hooks = null,
        protected readonly ?AttributeRegistry $attributes = null,
        protected readonly ?ExtensionRegistry $extensions = null,
    ) {}
    
    // === Выполнение ===
    
    /**
     * Выполнить запрос синхронно
     */
    public function send(RequestInterface $request): ExecutionResult;
    
    /**
     * Выполнить запрос асинхронно
     */
    public function sendAsync(RequestInterface $request): PromiseInterface;
    
    /**
     * Batch выполнение
     */
    public function batch(RequestCollection|array $requests): BatchExecutor;
    
    /**
     * Pool выполнение (массовое)
     */
    public function pool(
        iterable $requests,
        int|callable|ConcurrencyResolverInterface $concurrency = 5,
    ): PoolExecutor;
    
    // === Тестирование ===
    
    /**
     * Настроить mock responses
     */
    public function fake(array $responses): static;
    
    /**
     * Проверить что запрос был отправлен
     */
    public function assertSent(string $requestClass, ?callable $callback = null): void;
    
    /**
     * Проверить что ничего не отправлено
     */
    public function assertNothingSent(): void;
    
    // === Extensions ===
    
    /**
     * Зарегистрировать расширение в runtime
     * checkDependencies() → register()
     */
    public function registerExtension(ExtensionInterface $extension): static;
    
    /**
     * Получить расширение по имени
     */
    public function getExtension(string $name): ?ExtensionInterface;
    
    // === Расширение (override) ===
    
    /**
     * Определить условие failed (override в конкретном клиенте)
     */
    protected function hasRequestFailed(ProviderResponse $response): bool;
    
    /**
     * Определить условие retry (override)
     */
    protected function shouldRetry(ProviderResponse $response, int $attempt): bool;
    
    /**
     * Создать exception из response (override)
     */
    protected function getRequestException(ProviderResponse $response): ?RequestException;
}
```

---

## AbstractRequest

Базовый класс запроса. Декларативное описание через атрибуты.
Внутри разделён на:
- `RequestSpec` — кэшированная декларация (атрибуты и метаданные).
- `RequestOptions` — runtime‑настройки (override‑ы, `with*`).

Важно: `with*` методы возвращают `RequestExecution`, а не `AbstractRequest`.
Кастомные методы запроса должны вызываться **до** `with*`.

`AbstractRequest` реализует `RequestOptionsProviderInterface` и предоставляет `getOptions(): RequestOptions`.
Основные `with*` из `RequestOptions`:
`withBaseUrl`, `withCache/withoutCache`, `withRetry/withoutRetry`, `withoutAuth`,
`withDelay/withoutDelay`, `withIdempotencyKey`, `withRateLimit`, `withTimeout`,
`withTraceId`, `withRole`, `withHeader`.

```php
abstract class AbstractRequest implements RequestInterface
{
    protected ?ClientInterface $client = null;
    
    // === Выполнение ===
    
    /**
     * Синхронная отправка
     */
    public function send(): ExecutionResult;
    
    /**
     * Асинхронная отправка
     */
    public function sendAsync(): PromiseInterface;
    
    // === Client Resolution ===
    
    /**
     * Установить клиент (вызывается Resource или вручную)
     */
    public function setClient(ClientInterface $client): static;
    
    /**
     * Резолв клиента (override в BaseRequest конкретного SDK)
     */
    protected function resolveClient(): ClientInterface;
    
    // === URL Resolution ===
    
    /**
     * Динамический endpoint (override)
     */
    protected function resolveEndpoint(): ?string;
    
    /**
     * Альтернативный baseUrl (override)
     */
    protected function resolveBaseUrl(): ?string;
    
    /**
     * Runtime override baseUrl
     */
    public function withBaseUrl(string $url): RequestExecution;
    
    // === Modifiers (immutable, return RequestExecution) ===
    
    public function withoutCache(): RequestExecution;
    public function withCache(?int $ttl = null): RequestExecution;
    public function withCacheWriteOnly(?int $ttl = null): RequestExecution;
    public function withCacheReadOnly(?int $ttl = null): RequestExecution;
    public function withoutRetry(): RequestExecution;
    public function withRetry(int $attempts): RequestExecution;
    public function withoutAuth(): RequestExecution;
    public function withDelay(int $ms): RequestExecution;
    public function withoutDelay(): RequestExecution;
    public function withIdempotencyKey(string $key): RequestExecution;
    public function withRateLimit(int $limit, int $period, RateLimitBehavior $behavior = RateLimitBehavior::Wait): RequestExecution;
    public function withTimeout(int $seconds, ?int $connectTimeout = null): RequestExecution;
    public function withTraceId(string $traceId): RequestExecution;
    public function withRole(RequestRole $role): RequestExecution;
    public function withHeader(string $name, string $value): RequestExecution;
    
    // === Lifecycle Hooks (override) ===
    // Сигнатуры совпадают по смыслу; beforeHydrate дополнительно принимает $data
    
    protected function beforeSend(PipelineContext $context): void;
    protected function afterResponse(PipelineContext $context): void;
    protected function beforeHydrate(PipelineContext $context, array $data): array;  // return modified data
    protected function afterHydrate(PipelineContext $context): void;
    
    // === Validation ===
    
    /**
     * Кастомные сообщения валидации (override)
     */
    protected function validationMessages(): array;
    
    // === Helpers ===
    
    public function getMethod(): HttpMethod;
    public function getEndpoint(): string;
    public function getResponseType(): ?string;
}
```

---

## RequestExecution

Обёртка над запросом, которая хранит runtime‑настройки (`RequestOptions`)
и параметры пагинации (`PaginationOptions`) как отдельный VO.
Используется для цепочек `with*()->send()`, при этом оригинальный запрос не клонируется.

Правило для DX:
- кастомные методы запроса вызываются **до** `with*` (после `with*` доступен только API `RequestExecution`).

`RequestExecution` реализует `RequestExecutionInterface` и `RequestOptionsProviderInterface`.

## AbstractResource

Навигация по API. Группировка endpoints.

```php
abstract class AbstractResource
{
    public function __construct(
        protected readonly ClientInterface $client,
    ) {}
    
    /**
     * Создать вложенный ресурс
     */
    protected function resource(string $class): AbstractResource;
    
    /**
     * Создать запрос с привязкой к клиенту
     */
    protected function request(string $class, mixed ...$args): AbstractRequest;
}
```

**Пример использования:**

```php
class OrdersResource extends AbstractResource
{
    public function get(string $id): GetOrder
    {
        return $this->request(GetOrder::class, $id);
    }
    
    public function items(): OrderItemsResource
    {
        return $this->resource(OrderItemsResource::class);
    }
}
```

---

## Collections

Типизированные коллекции, используемые в Composite/DependsOn и результатах.

```php
final class RequestCollection
{
    public static function make(array $items): self;
    public function get(string $class): ?RequestInterface;
    public function all(): array;
}

// Принимает классы, инстансы и callable (ленивое создание).
// Неподдерживаемые элементы приводят к ConfigurationException при выполнении.

final class ResultCollection
{
    public function get(string $class): ?ExecutionResult;
    public function hasErrors(): bool;
    public function all(): array;
}

// Доступ по классу и проверка наличия ошибок.

final class ErrorCollection
{
    public function first(): ?RequestError;
    public function all(): array;
}
```

### ExecutionResult

Базовый результат pipeline. Поля: data, status (ResultStatus), errors (ErrorCollection),
validationErrors, debug, traceId, audit, meta, nested. Методы: isSuccess(), isPartial(),
isFailed(), hasData(), hasErrors(), throw().

---

## AbstractDto

Базовый DTO с валидацией.

```php
abstract readonly class AbstractDto implements DtoInterface, ValidatableInterface
{
    use ValidatesAttributes;
    
    /**
     * Создание из массива или объекта с toArray()
     * Использует Hydrator::default()
     */
    public static function from(array|object $data): static
    {
        return Hydrator::default()->hydrate($data, static::class);
    }
}
```

---

## AbstractResponseDto

DTO для response с поддержкой computed полей.

```php
abstract readonly class AbstractResponseDto extends AbstractDto implements ResponseDtoInterface
{
    /**
     * Вычисляемые поля перед гидрацией
     * Context nullable для поддержки from() без context
     */
    public static function computed(array $data, ?PipelineContext $context = null): array
    {
        return $data; // default: без изменений
    }
}
```

**Пример:**

```php
readonly class OrderResponse extends AbstractResponseDto
{
    public function __construct(
        public string $id,
        public Money $total,
        public string $displayTotal, // computed
    ) {}
    
    public static function computed(array $data, ?PipelineContext $context = null): array
    {
        $data['displayTotal'] = number_format($data['total'] / 100, 2) . ' ₽';
        return $data;
    }
}
```

---

## ValidatesAttributes

Trait для унификации валидации в Request и DTO.

```php
trait ValidatesAttributes
{
    public function validate(): static
    {
        $result = Validator::check($this);
        if ($result->failed()) {
            throw new ValidationException($result->errors());
        }
        return $this;
    }
    
    public function isValid(): bool
    {
        return Validator::check($this)->passed();
    }
    
    public function errors(): array
    {
        return Validator::check($this)->errors();
    }
}
```

Используется в `AbstractDto` и `AbstractRequest`.

В pipeline ошибки валидации перехватываются и возвращаются через
`ExecutionResult` как `ValidationError[]`. Исключения выбрасываются
только при `throwOnErrors` или явном `result->throw()`.

---

## Validator

Единая точка выполнения валидации. Реализует `ValidatorInterface` и
используется `ValidatesAttributes` и pipeline.

```php
final class Validator implements ValidatorInterface
{
    public static function check(object $value): ValidationResult;
}
```

---

## Резюме

| Класс | Назначение | Методы для override |
|-------|------------|---------------------|
| `AbstractClient` | Точка входа, конфигурация | `hasRequestFailed()`, `shouldRetry()`, `getRequestException()` |
| `AbstractRequest` | Описание запроса | `resolveClient()`, `resolveEndpoint()`, `resolveBaseUrl()`, hooks |
| `RequestSpec` | Декларация запроса (атрибуты) | — |
| `RequestOptions` | Runtime‑настройки запроса | — |
| `RequestExecution` | Обёртка запроса + options | — |
| `AbstractResource` | Навигация | — |
| `AbstractDto` | Базовый DTO | `from()`, `validate()` |
| `AbstractResponseDto` | Response DTO | `computed()` |
| `ValidatesAttributes` | Trait валидации | — |
