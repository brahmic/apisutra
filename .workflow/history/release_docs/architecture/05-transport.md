# Transport — HTTP абстракция

Слой абстракции над HTTP-клиентом.

---

## Архитектура

```
AbstractClient
    ↓
TransportInterface ←─── DI Container
    ↓
┌───────────────┬───────────────┐
│ HttpTransport │ MockTransport │
│  (production) │   (testing)   │
└───────────────┴───────────────┘
    ↓                   ↓
 Guzzle/PSR-18     Fake responses
```

---

## TransportInterface

```php
interface TransportInterface
{
    /**
     * Синхронная отправка
     */
    public function send(PreparedRequest $request): ProviderResponse;
    
    /**
     * Асинхронная отправка
     */
    public function sendAsync(PreparedRequest $request): PromiseInterface;
}
```

---

## HttpTransport

Production реализация.

```php
class HttpTransport implements TransportInterface
{
    public function __construct(
        private readonly ClientInterface $httpClient,  // PSR-18
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {}
    
    public function send(PreparedRequest $request): ProviderResponse
    {
        $psrRequest = $this->buildPsrRequest($request);
        $psrResponse = $this->httpClient->sendRequest($psrRequest);
        
        return $this->buildProviderResponse($psrResponse, $request);
    }
    
    public function sendAsync(PreparedRequest $request): PromiseInterface
    {
        // Guzzle async или promise wrapper
    }
    
    private function buildPsrRequest(PreparedRequest $request): \Psr\Http\Message\RequestInterface;
    private function buildProviderResponse(ResponseInterface $response, PreparedRequest $request): ProviderResponse;
}
```

Асинхронность реализуется через PromiseInterface (Guzzle Promises).
SDK не управляет event loop; выполнение промиса остаётся на стороне пользователя.

---

## MockTransport

Testing реализация.

```php
class MockTransport implements TransportInterface
{
    private array $responses = [];
    private array $recorded = [];
    private bool $preventStray = false;
    
    /**
     * Настроить mock responses
     */
    public function fake(array $responses): void;
    
    /**
     * Запретить немоканные запросы
     */
    public function preventStrayRequests(): void;
    
    public function send(PreparedRequest $request): ProviderResponse
    {
        $this->recorded[] = $request;
        
        $response = $this->findResponse($request);
        
        if ($response === null && $this->preventStray) {
            throw new UnmockedRequestException($request);
        }
        
        return $response ?? $this->defaultResponse();
    }
    
    /**
     * Получить записанные запросы
     */
    public function getRecorded(): array;
    
    /**
     * Assertions
     */
    public function assertSent(string $requestClass, ?callable $callback = null): void;
    public function assertNotSent(string $requestClass): void;
    public function assertNothingSent(): void;
    
    private function findResponse(PreparedRequest $request): ?ProviderResponse;
}
```

---

## PreparedRequest

Готовый к отправке запрос (результат сериализации).

```php
readonly class PreparedRequest
{
    public function __construct(
        public HttpMethod $method,
        public string $url,              // Полный URL
        public array $headers = [],
        public ?string $body = null,     // JSON string или null
        public ?StreamInterface $stream = null,  // Для multipart
        public array $meta = [],         // Debug info
    ) {}
    
    /**
     * Создать копию с изменениями
     */
    public function with(
        ?array $headers = null,
        ?string $body = null,
    ): self;
    
    /**
     * Добавить header
     */
    public function withHeader(string $name, string $value): self;
}
```

---

## ProviderResponse

Ответ от API.

```php
readonly class ProviderResponse
{
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
        public PreparedRequest $request,  // Для debug
        public float $duration,           // Время выполнения (ms)
    ) {}
    
    /**
     * Декодировать JSON body
     */
    public function json(?string $key = null): mixed;
    
    /**
     * Проверка статуса
     */
    public function isSuccess(): bool;      // 2xx
    public function isClientError(): bool;  // 4xx
    public function isServerError(): bool;  // 5xx
    
    /**
     * Получить header
     */
    public function header(string $name): ?string;
    public function headers(): array;
}
```

---

## MockClient Facade

Фасад для настройки тестирования.

```php
class MockClient
{
    private static ?MockTransport $globalTransport = null;
    
    /**
     * Установить глобальный mock (для DI)
     */
    public static function global(): MockTransport
    {
        if (self::$globalTransport === null) {
            self::$globalTransport = new MockTransport();
        }
        return self::$globalTransport;
    }
    
    /**
     * Уничтожить глобальный mock
     */
    public static function destroyGlobal(): void
    {
        self::$globalTransport = null;
    }
    
    /**
     * Проверить, активен ли глобальный mock
     */
    public static function hasGlobal(): bool
    {
        return self::$globalTransport !== null;
    }
}
```

---

## DI Integration

### Laravel ServiceProvider

```php
class SdkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TransportInterface::class, function ($app) {
            // В тестах — MockTransport
            if (MockClient::hasGlobal()) {
                return MockClient::global();
            }
            
            // В production — HttpTransport
            return new HttpTransport(
                httpClient: $app->make(ClientInterface::class),
                requestFactory: $app->make(RequestFactoryInterface::class),
                streamFactory: $app->make(StreamFactoryInterface::class),
            );
        });
    }
}
```

### В тестах

```php
class OrderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        MockClient::global()->fake([
            GetOrder::class => ['id' => '123', 'status' => 'active'],
        ]);
    }
    
    protected function tearDown(): void
    {
        MockClient::destroyGlobal();
        parent::tearDown();
    }
    
    public function test_get_order(): void
    {
        // Request создаётся напрямую
        $result = (new GetOrder('123'))->send();
        
        // Transport из DI — MockTransport
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('123', $result->data->id);
        
        MockClient::global()->assertSent(GetOrder::class);
    }
}
```

---

## MockResponse Helper

```php
readonly class MockResponse
{
    public static function ok(array $data = []): ProviderResponse;
    public static function created(array $data = []): ProviderResponse;
    public static function noContent(): ProviderResponse;
    public static function badRequest(array $errors = []): ProviderResponse;
    public static function unauthorized(): ProviderResponse;
    public static function forbidden(): ProviderResponse;
    public static function notFound(): ProviderResponse;
    public static function unprocessable(array $errors = []): ProviderResponse;
    public static function tooManyRequests(int $retryAfter = 60): ProviderResponse;
    public static function serverError(): ProviderResponse;
    
    /**
     * Произвольный response
     */
    public static function make(
        int $status,
        array $body = [],
        array $headers = [],
    ): ProviderResponse;
}
```

---

## Резюме

| Компонент | Назначение |
|-----------|------------|
| `TransportInterface` | Контракт HTTP |
| `HttpTransport` | Production: Guzzle/PSR-18 |
| `MockTransport` | Testing: fake responses |
| `PreparedRequest` | Готовый запрос |
| `ProviderResponse` | Ответ API |
| `MockClient` | Facade для тестов |
| `MockResponse` | Helpers для создания responses |
