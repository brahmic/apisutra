# Extension Points — Расширение SDK

Как создавать SDK для конкретных провайдеров.

---

## Структура SDK пакета

```
vendor/my-sdk/
├── src/
│   ├── MyClient.php              # Extends AbstractClient
│   ├── BaseRequest.php           # Extends AbstractRequest + resolveClient()
│   ├── Resources/
│   │   ├── OrdersResource.php
│   │   └── UsersResource.php
│   ├── Requests/
│   │   ├── Orders/
│   │   │   ├── GetOrder.php
│   │   │   └── CreateOrder.php
│   │   └── Users/
│   │       └── GetUser.php
│   ├── DTOs/
│   │   ├── OrderDto.php
│   │   └── UserDto.php
│   ├── Casts/
│   │   └── StatusCast.php
│   └── Enums/
│       └── OrderStatus.php
├── config/
│   └── my-sdk.php                # Laravel config
└── composer.json
```

---

## 1. Client

```php
<?php

declare(strict_types=1);

namespace Vendor\MySdk;

use Brahmic\ApiSutra\AbstractClient;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Transport\TransportInterface;

class MyClient extends AbstractClient
{
    public function __construct(
        ClientConfig $config,
        TransportInterface $transport,
    ) {
        parent::__construct($config, $transport);
    }
    
    /**
     * Фабрика с defaults
     */
    public static function make(string $apiKey): self
    {
        return new self(
            config: new ClientConfig(
                baseUrl: 'https://api.example.com/v1',
                auth: new ApiKeyAuthenticator($apiKey),
                namingStrategy: NamingStrategy::SnakeCase,
            ),
            transport: app(TransportInterface::class),
        );
    }
    
    // === Resources ===
    
    public function orders(): OrdersResource
    {
        return new OrdersResource($this);
    }
    
    public function users(): UsersResource
    {
        return new UsersResource($this);
    }
    
    // === Кастомизация поведения ===
    
    /**
     * Провайдер возвращает 200, ошибки в body
     */
    protected function hasRequestFailed(ProviderResponse $response): bool
    {
        if (!$response->isSuccess()) {
            return true;
        }
        
        // Кастомная логика
        return $response->json('success') === false;
    }
    
    /**
     * Кастомная логика retry
     */
    protected function shouldRetry(ProviderResponse $response, int $attempt): bool
    {
        // Retry при temporary_error в body
        if ($response->json('error.code') === 'temporary_error') {
            return $attempt < 3;
        }
        
        return parent::shouldRetry($response, $attempt);
    }
}
```

---

## 2. BaseRequest

Базовый класс для всех запросов SDK.

```php
<?php

declare(strict_types=1);

namespace Vendor\MySdk;

use Brahmic\ApiSutra\AbstractRequest;
use Brahmic\ApiSutra\Contracts\ClientInterface;

abstract class BaseRequest extends AbstractRequest
{
    /**
     * Резолв клиента из DI
     */
    protected function resolveClient(): ClientInterface
    {
        return app(MyClient::class);
    }
}
```

**Все запросы SDK наследуют `BaseRequest`:**

```php
<?php

declare(strict_types=1);

namespace Vendor\MySdk\Requests\Orders;

use Vendor\MySdk\BaseRequest;

#[Get('/orders/{id}')]
#[Returns(OrderDto::class)]
readonly class GetOrder extends BaseRequest
{
    public function __construct(
        public string $id,
    ) {}
}
```

---

## 3. Resource

```php
<?php

declare(strict_types=1);

namespace Vendor\MySdk\Resources;

use Brahmic\ApiSutra\AbstractResource;

class OrdersResource extends AbstractResource
{
    public function get(string $id): GetOrder
    {
        return $this->request(GetOrder::class, $id);
    }
    
    public function create(CreateOrderInput $input): CreateOrder
    {
        return $this->request(CreateOrder::class, $input);
    }
    
    public function list(int $page = 1, int $limit = 20): ListOrders
    {
        return $this->request(ListOrders::class, $page, $limit);
    }
    
    /**
     * Вложенный ресурс
     */
    public function items(string $orderId): OrderItemsResource
    {
        return $this->resource(OrderItemsResource::class, $orderId);
    }
}
```

---

## 4. DTO

```php
<?php

declare(strict_types=1);

namespace Vendor\MySdk\DTOs;

use Brahmic\ApiSutra\AbstractResponseDto;

readonly class OrderDto extends AbstractResponseDto
{
    public function __construct(
        public string $id,
        public OrderStatus $status,
        public Money $total,
        
        #[Nested(type: ItemDto::class)]
        public array $items,
        
        public ?CustomerDto $customer,
        
        #[From('created_at')]
        public Carbon $createdAt,
    ) {}
    
    /**
     * Вычисляемые поля
     */
    public static function computed(array $data, ?PipelineContext $context = null): array
    {
        $data['itemCount'] = count($data['items'] ?? []);
        return $data;
    }
}
```

---

## Extension lifecycle

`boot()` вызывается при первом реальном использовании зарегистрированного
компонента (handler/каст/хук), а не при регистрации.

---

## 5. Кастомный Cast

```php
<?php

declare(strict_types=1);

namespace Vendor\MySdk\Casts;

use Brahmic\ApiSutra\Contracts\CastInterface;

readonly class StatusCast implements CastInterface
{
    public function hydrate(mixed $value, ?PipelineContext $context = null): OrderStatus
    {
        return OrderStatus::from($value);
    }
    
    public function serialize(mixed $value, ?PipelineContext $context = null): string
    {
        return $value->value;
    }
}
```

**Регистрация в конфиге:**

```php
new ClientConfig(
    casts: [
        OrderStatus::class => StatusCast::class,
    ],
);
```

---

## 6. Кастомный Authenticator

```php
<?php

declare(strict_types=1);

namespace Vendor\MySdk\Auth;

use Brahmic\ApiSutra\Contracts\AuthenticatorInterface;
use Brahmic\ApiSutra\Contracts\CacheAwareInterface;
use Brahmic\ApiSutra\Contracts\ResponseDtoInterface;

class HmacAuthenticator implements AuthenticatorInterface, CacheAwareInterface
{
    private ?CacheInterface $cache = null;
    
    public function __construct(
        private readonly string $apiKey,
        private readonly string $secret,
    ) {}
    
    public function authenticate(PreparedRequest $request): PreparedRequest
    {
        $timestamp = time();
        $signature = $this->sign($request, $timestamp);
        
        return $request
            ->withHeader('X-Api-Key', $this->apiKey)
            ->withHeader('X-Timestamp', (string) $timestamp)
            ->withHeader('X-Signature', $signature);
    }
    
    public function shouldRefresh(): bool
    {
        return false; // HMAC не требует refresh
    }
    
    public function getRefreshRequest(): ?RequestInterface
    {
        return null;
    }
    
    public function processTokenResponse(ResponseDtoInterface $response): void
    {
        // N/A
    }
    
    public function setCache(CacheInterface $cache): void
    {
        $this->cache = $cache;
    }
    
    public function getCacheKey(): string
    {
        return 'hmac_' . md5($this->apiKey);
    }
    
    private function sign(PreparedRequest $request, int $timestamp): string
    {
        $data = $request->method->value . $request->url . $timestamp;
        return hash_hmac('sha256', $data, $this->secret);
    }
}
```

---

## 7. Кастомный Hook

```php
<?php

declare(strict_types=1);

namespace Vendor\MySdk\Hooks;

use Brahmic\ApiSutra\Contracts\BeforeSendHookInterface;

class AddCorrelationId implements BeforeSendHookInterface
{
    public function handle(PipelineContext $context): void
    {
        $context->preparedRequest = $context->preparedRequest->withHeader(
            'X-Correlation-Id',
            $context->traceId,
        );
    }
}
```

**Регистрация:**

```php
// В ServiceProvider
$hooks = app(HookRegistry::class);
$hooks->on(Hook::BeforeSend, AddCorrelationId::class);
```

---

## 8. Кастомный Attribute

**Атрибут:**

```php
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Encrypt
{
    public function __construct(
        public string $algorithm = 'aes-256-cbc',
    ) {}
}
```

**Handler:**

```php
class EncryptHandler implements AttributeHandlerInterface
{
    public function handle(
        object $attribute,
        ReflectionProperty $reflection,
        PipelineContext $context,
    ): void {
        // Логика шифрования
    }
}
```

**Регистрация:**

```php
$attributes = app(AttributeRegistry::class);
$attributes->register(Encrypt::class, EncryptHandler::class);
```

---

## 9. Laravel ServiceProvider

```php
<?php

declare(strict_types=1);

namespace Vendor\MySdk;

use Illuminate\Support\ServiceProvider;

class MySdkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/my-sdk.php', 'my-sdk');
        
        $this->app->singleton(MyClient::class, function ($app) {
            return MyClient::make(
                apiKey: config('my-sdk.api_key'),
            );
        });
    }
    
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/my-sdk.php' => config_path('my-sdk.php'),
        ], 'my-sdk-config');
    }
}
```

---

## Checklist расширения

| Компонент | Обязательно | Описание |
|-----------|-------------|----------|
| `MyClient` | ✅ | Extends `AbstractClient` |
| `BaseRequest` | ✅ | Extends `AbstractRequest`, `resolveClient()` |
| `Resources/*` | ✅ | Навигация по API |
| `Requests/*` | ✅ | Extends `BaseRequest` |
| `DTOs/*` | ✅ | Extends `AbstractDto`/`AbstractResponseDto` |
| `Casts/*` | ❌ | Implements `CastInterface` |
| `Auth/*` | ❌ | Implements `AuthenticatorInterface` |
| `Hooks/*` | ❌ | Implements `HookInterface` |
| `ServiceProvider` | ❌ | Laravel интеграция |
