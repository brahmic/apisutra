# Интеграция с Laravel

## Обзор

SDK предоставляет интеграцию с Laravel для автоматического DI запросов в контроллеры.

## RequestFactoryInterface

Core SDK предоставляет интерфейс фабрики:

```php
use Brahmic\ApiSutra\Contracts\Core\RequestInterface;
use Illuminate\Http\Request;

interface RequestFactoryInterface
{
    /**
     * Создать Request с заполнением из источника данных
     */
    public function make(string $requestClass, Request|array $source): RequestInterface;
}
```

**Режим array:**
- Поддерживаются два формата:
  - упрощённый: `['field' => 'value']` (используется как `query` и `body`);
  - структурированный: `['route' => [], 'query' => [], 'body' => [], 'headers' => [], 'files' => []]`.
- HTTP‑method берётся из атрибутов запроса (`#[Get]`, `#[Post]` и т.п.).
- Для POST‑запросов параметры из `query` используются только при `#[Query]`.

## Автоматический DI

После подключения ServiceProvider, запросы автоматически резолвятся из контейнера:

```php
class OrderController extends Controller
{
    public function show(GetOrder $request): JsonResponse
    {
        // $request уже заполнен из HTTP request:
        // - #[Path] → route parameters
        // - #[Query] → query string
        // - #[Body] → request body
        
        return response()->json($request->send()->data);
    }
    
    public function index(ListOrders $request): JsonResponse
    {
        return response()->json($request->send()->data);
    }
}
```

## Маппинг параметров

**Convention over Configuration:** атрибуты опциональны.

| Свойство | Источник в Laravel |
|----------|-------------------|
| Совпадает с `{placeholder}` в URL | `$request->route('param')` |
| Остальные (GET) | `$request->query('param')` |
| Остальные (POST/PUT) | `$request->input('param')` |

**Явный маппинг (если нужен):**

| Атрибут | Назначение |
|---------|-----------|
| `#[Path('custom')]` | Кастомное имя path-параметра |
| `#[Query]` | Принудительно в query string |
| `#[Body]` | Принудительно в body |

## DI клиента и ресурсов

Клиент и ресурсы также доступны через DI:

```php
class OrderController extends Controller
{
    public function __construct(
        private readonly ApiClient $client,
    ) {}
    
    // Или в методе
    public function show(OrdersResource $orders, string $id): JsonResponse
    {
        return response()->json($orders->get($id)->send()->data);
    }
}
```

## Регистрация клиента

```php
// В AppServiceProvider или отдельном провайдере
$this->app->singleton(ApiClient::class, function ($app) {
    return new ApiClient(
        config: new ClientConfig(
            baseUrl: config('services.api.url'),
            cache: $app->make(CacheInterface::class),
            logger: $app->make(LoggerInterface::class),
            // Автоматически из Laravel
            debug: config('app.debug'),
            environment: Environment::from($app->environment()),
        ),
    );
});
```

## Environment из Laravel

SDK автоматически определяет окружение:

```php
use Brahmic\ApiSutra\Enums\Configuration\Environment;

// Маппинг Laravel → SDK
$environment = match ($app->environment()) {
    'local' => Environment::Local,
    'testing' => Environment::Testing,
    'staging' => Environment::Staging,
    default => Environment::Production,
};
```

**Влияние Environment:**

| Environment | Debug | MetadataCache | Logging |
|-------------|-------|---------------|---------|
| `Local` | On | Off | Verbose |
| `Testing` | On | Off | Minimal |
| `Staging` | Off | On | Standard |
| `Production` | Off | On | Errors only |

`MetadataCache` — это `AttributeMetadataCache`, влияет на скорость Reflection при сериализации/гидрации.

**Helper в SDK:**

```php
// Или через хелпер (если SDK поставляется с Laravel)
$config = ClientConfig::fromLaravel([
    'baseUrl' => config('services.api.url'),
    // debug и environment подставятся автоматически
]);
```

## Тестирование

Factory легко мокается в тестах:

```php
$this->mock(RequestFactoryInterface::class, function ($mock) {
    $mock->shouldReceive('make')
        ->with(GetOrder::class)
        ->andReturn(new GetOrder(orderId: 'test-123'));
});
```
