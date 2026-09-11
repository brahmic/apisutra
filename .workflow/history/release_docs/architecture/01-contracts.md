# Контракты (Интерфейсы)

Все публичные интерфейсы SDK.

---

## Transport

### TransportInterface

```php
interface TransportInterface
{
    /**
     * Синхронная отправка запроса
     */
    public function send(PreparedRequest $request): ProviderResponse;
    
    /**
     * Асинхронная отправка запроса
     */
    public function sendAsync(PreparedRequest $request): PromiseInterface;
}
```

---

## Client

### ClientInterface

```php
interface ClientInterface
{
    /**
     * Выполнить запрос синхронно
     */
    public function send(RequestInterface $request): ExecutionResult;
    
    /**
     * Выполнить запрос асинхронно
     */
    public function sendAsync(RequestInterface $request): PromiseInterface;
    
    /**
     * Получить конфигурацию
     */
    public function getConfig(): ClientConfig;
}
```

---

## Request

### RequestInterface

```php
interface RequestInterface
{
    /**
     * HTTP метод (из атрибута или переопределения)
     */
    public function getMethod(): HttpMethod;
    
    /**
     * Endpoint path (из атрибута или resolveEndpoint())
     */
    public function getEndpoint(): string;
    
    /**
     * Тип ответа (из #[Returns])
     */
    public function getResponseType(): ?string;
}
```

### CompositeRequestInterface

```php
interface CompositeRequestInterface extends RequestInterface
{
    /**
     * Дочерние запросы для выполнения
     */
    public function requests(): RequestCollection;
    
    /**
     * Агрегация результатов в единый DTO
     */
    public function aggregate(ResultCollection $results, PipelineContext $ctx): mixed;
}
```

### DependsOnRequestInterface

```php
interface DependsOnRequestInterface extends RequestInterface
{
    /**
     * Запросы-зависимости
     */
    public function dependencies(): RequestCollection;
    
    /**
     * Обработка результатов зависимостей перед основным запросом
     */
    public function processDependencies(ResultCollection $results, PipelineContext $ctx): void;
}
```

### PaginableInterface

```php
interface PaginableInterface
{
    public function withPage(int $page): RequestExecutionInterface;
    public function withLimit(int $limit): RequestExecutionInterface;
    
    // Опционально — для cursor-based
    public function withCursor(?string $cursor): RequestExecutionInterface;
    
    // Извлечение меты из ответа (приоритет над атрибутом)
    public function extractMeta(array $response): PaginationMeta;
}
```

---

## Response & Result

### ResultInterface

```php
interface ResultInterface
{
    public function isSuccess(): bool;
    public function isFailed(): bool;
    public function hasData(): bool;
    public function hasErrors(): bool;
}
```

### ResultMeta

```php
interface ResultMeta
{
    // Маркер для PaginationMeta, BatchMeta
}
```

### DtoInterface

```php
interface DtoInterface
{
    /**
     * Фабричный метод создания из массива или объекта
     * Объект должен иметь метод toArray() (Laravel Model, Arrayable)
     */
    public static function from(array|object $data): static;
}
```

### ResponseDtoInterface

```php
interface ResponseDtoInterface extends DtoInterface
{
    /**
     * Вычисляемые поля перед гидрацией
     * Context nullable для поддержки from() без context
     */
    public static function computed(array $data, ?PipelineContext $context = null): array;
}
```

### ValidatableInterface

```php
interface ValidatableInterface
{
    /**
     * Валидировать по правилам #[Validate]
     * @throws ValidationException при ошибках
     */
    public function validate(): static;
    
    /**
     * Проверить валидность без exception
     */
    public function isValid(): bool;
    
    /**
     * Получить ошибки валидации
     */
    public function errors(): array;
}
```

---

## Authentication

### AuthenticatorInterface

```php
interface AuthenticatorInterface
{
    /**
     * Добавить auth данные к запросу
     */
    public function authenticate(PreparedRequest $request): PreparedRequest;
    
    /**
     * Нужен ли refresh токена (до запроса)
     */
    public function shouldRefresh(): bool;
    
    /**
     * Запрос на refresh токена
     */
    public function getRefreshRequest(): ?RequestInterface;
    
    /**
     * Обработка ответа refresh
     */
    public function processTokenResponse(ResponseDtoInterface $response): void;
}
```

### CacheAwareInterface

```php
interface CacheAwareInterface
{
    /**
     * Установить кеш для хранения токенов
     */
    public function setCache(CacheInterface $cache): void;
    
    /**
     * Ключ кеша для токена
     */
    public function getCacheKey(): string;
}
```

---

## Casts

### CastInterface

```php
interface CastInterface
{
    /**
     * Преобразование при гидрации (API → DTO)
     * Context nullable для поддержки from() без context
     */
    public function hydrate(mixed $value, ?PipelineContext $context = null): mixed;
    
    /**
     * Преобразование при сериализации (DTO → API)
     */
    public function serialize(mixed $value, ?PipelineContext $context = null): mixed;
}
```

---

## Hooks

### HookInterface

```php
interface HookInterface
{
    /**
     * Выполнить хук
     */
    public function handle(PipelineContext $context): void;
}
```

### BeforeSendHookInterface

```php
interface BeforeSendHookInterface extends HookInterface
{
    public function handle(PipelineContext $context): void;
}
```

### AfterResponseHookInterface

```php
interface AfterResponseHookInterface extends HookInterface
{
    public function handle(PipelineContext $context): void;
}
```

### BeforeHydrateHookInterface

```php
interface BeforeHydrateHookInterface extends HookInterface
{
    /**
     * @return array Модифицированные данные
     */
    public function handle(PipelineContext $context): array;
}
```

### AfterHydrateHookInterface

```php
interface AfterHydrateHookInterface extends HookInterface
{
    public function handle(PipelineContext $context): void;
}
```

---

## Attributes

### AttributeHandlerInterface

```php
interface AttributeHandlerInterface
{
    /**
     * Обработать атрибут
     */
    public function handle(
        object $attribute,
        ReflectionProperty|ReflectionClass $reflection,
        PipelineContext $context,
    ): void;
}
```

---

## Validation

### ValidatorInterface

```php
interface ValidatorInterface
{
    /**
     * Валидировать объект и вернуть результат
     */
    public function check(object $value): ValidationResult;
}
```

### ValidationResult

```php
interface ValidationResult
{
    public function passed(): bool;
    public function failed(): bool;
    public function errors(): array; // array<ValidationError>
}
```

---

## Concurrency

### ConcurrencyResolverInterface

```php
interface ConcurrencyResolverInterface
{
    /**
     * Определить уровень параллелизма
     * 
     * @param int $pending Количество ожидающих запросов
     * @param int $completed Количество выполненных
     */
    public function getConcurrency(int $pending, int $completed): int;
}
```

---

## Retry

### RetryHandlerInterface

```php
interface RetryHandlerInterface
{
    public function handle(
        PreparedRequest $request,
        PipelineContext $context,
        RetryConfig $config,
        int $attempt,
    ): ProviderResponse;
}
```

---

## Factory

### RequestFactoryInterface

```php
interface RequestFactoryInterface
{
    /**
     * Создать и заполнить Request из источника данных
     */
    public function make(string $requestClass, Request|array $source): RequestInterface;
}
```

---

## Extensions

### ExtensionInterface

```php
namespace Brahmic\ApiSutra\Contracts;

interface ExtensionInterface
{
    /**
     * Уникальное имя расширения
     */
    public function getName(): string;
    
    /**
     * Phase 1: декларация компонентов
     * Вызывается сразу при добавлении в Client
     */
    public function register(ExtensionContext $context): void;
    
    /**
     * Phase 2: инициализация
     * Вызывается lazy, при первом использовании
     */
    public function boot(ClientConfig $config): void;
    
    /**
     * Проверка зависимостей (php extensions, packages)
     */
    public function checkDependencies(): void;
    
    /**
     * Статус после boot (false если зависимости не удовлетворены)
     */
    public function isEnabled(): bool;
}
```

### ResponseHandlerInterface

```php
namespace Brahmic\ApiSutra\Contracts;

interface ResponseHandlerInterface
{
    /**
     * Может ли обработать данный response
     */
    public function supports(ProviderResponse $response): bool;
    
    /**
     * Обработать response
     */
    public function handle(ProviderResponse $response, PipelineContext $context): mixed;
}
```

---

## Резюме

| Категория | Интерфейсы |
|-----------|------------|
| Transport | `TransportInterface` |
| Client | `ClientInterface` |
| Request | `RequestInterface`, `CompositeRequestInterface`, `DependsOnRequestInterface`, `PaginableInterface` |
| Result | `ResultInterface`, `ResultMeta`, `DtoInterface`, `ResponseDtoInterface` |
| Auth | `AuthenticatorInterface`, `CacheAwareInterface` |
| Casts | `CastInterface` |
| Hooks | `HookInterface`, `Before/AfterSend`, `Before/AfterHydrate` |
| Attributes | `AttributeHandlerInterface` |
| Validation | `ValidatorInterface`, `ValidatableInterface` |
| Concurrency | `ConcurrencyResolverInterface` |
| Retry | `RetryHandlerInterface` |
| Factory | `RequestFactoryInterface` |
| Extensions | `ExtensionInterface`, `ResponseHandlerInterface` |
