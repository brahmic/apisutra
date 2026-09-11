# Хуки и HookRegistry

## Обзор

SDK предоставляет два способа регистрации обработчиков хуков:
- **Атрибуты на запросе** — для специфичной логики конкретного запроса
- **Централизованная регистрация** — для общей логики на группу запросов или DTO

## enum Hook

Namespace: `Brahmic\ApiSutra\Enums\Hooks\Hook`.

```php
enum Hook: string
{
    case BeforeSend = 'before_send';
    case AfterResponse = 'after_response';
    case BeforeHydrate = 'before_hydrate';
    case AfterHydrate = 'after_hydrate';
}
```

## HookRegistry

Сервис для централизованной регистрации обработчиков.

### Создание

```php
$hooks = new HookRegistry(
    resolver: fn(string $class) => app($class),  // DI resolver
);
```

**Resolver:**
- Callable для резолвинга классов обработчиков
- Позволяет использовать DI контейнер (Laravel `app()`, etc.)
- По умолчанию: `fn(string $class) => new $class()`

### Регистрация обработчиков

**Глобально — для всех запросов:**

```php
$hooks->on(Hook::BeforeSend, AddTraceHeader::class);
$hooks->on(Hook::AfterResponse, LogResponse::class);
```

**Для конкретных Request:**

```php
$hooks->on(
    Hook::BeforeSend,
    AddSignature::class,
    for: [GetOrder::class, CreateOrder::class],
);
```

**Для DTO — обработка при гидрации:**

```php
$hooks->on(
    Hook::BeforeHydrate,
    UnwrapApiResponse::class,
    forDto: ApiWrapper::class,
);
```

### Передача в клиент

```php
$client = new Client(
    config: new ClientConfig(baseUrl: '...'),
    hooks: $hooks,
);
```

Или через метод:

```php
$client->hooks()->on(Hook::BeforeSend, AddTrace::class);
```

## Обработчики

### Интерфейсы

Все хуки принимают `PipelineContext` — он содержит всё необходимое:
- `$context->request` — текущий запрос
- `$context->preparedRequest` — сериализованный запрос (после prepare)
- `$context->response` — ответ API (после transport)
- `$context->dto` — результат гидрации (после hydrate)

```php
interface HookInterface
{
    public function handle(PipelineContext $context): void;
}

interface BeforeSendHookInterface extends HookInterface
{
    public function handle(PipelineContext $context): void;
}

interface AfterResponseHookInterface extends HookInterface
{
    public function handle(PipelineContext $context): void;
}

interface BeforeHydrateHookInterface extends HookInterface
{
    // Возвращает модифицированные данные для гидрации
    public function handle(PipelineContext $context): array;
}

interface AfterHydrateHookInterface extends HookInterface
{
    public function handle(PipelineContext $context): void;
}
```

### Пример обработчика с DI

```php
class LogResponse implements AfterResponseHookInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}
    
    public function handle(PipelineContext $context): void
    {
        $this->logger->info('API Response', [
            'request' => $context->request::class,
            'status' => $context->response->status,
            'traceId' => $context->traceId,
        ]);
    }
}
```

### Регистрация: класс или инстанс

```php
// Класс — резолвится через resolver (DI)
$hooks->on(Hook::AfterResponse, LogResponse::class);

// Инстанс — используется как есть
$hooks->on(Hook::AfterResponse, new LogResponse($customLogger));
```

## Порядок выполнения

```
1. Централизованные глобальные
2. Централизованные для Request/DTO
3. Атрибуты на запросе
4. Методы в классе запроса
```

От общего к частному. Порядок внутри одного уровня = порядок регистрации.

### HookPriority — контроль приоритета

Namespace: `Brahmic\ApiSutra\Enums\Hooks\HookPriority`.

```php
enum HookPriority: string
{
    case First = 'first';    // выполнить в начале уровня
    case Normal = 'normal';  // по порядку регистрации (default)
    case Last = 'last';      // выполнить в конце уровня
}
```

```php
// Логирование всегда последним (видит финальное состояние)
$hooks->on(Hook::BeforeSend, FinalLogger::class, priority: HookPriority::Last);

// Аутентификация всегда первой
$hooks->on(Hook::BeforeSend, AddAuth::class, priority: HookPriority::First);
```

---

## Naming — защита от дубликатов

При регистрации можно задать имя hook'а. Повторная регистрация с тем же именем **перезаписывает** предыдущий.

```php
// Первичная регистрация
$hooks->on(Hook::BeforeSend, SimpleLogger::class, name: 'logging');

// Перезапись — SimpleLogger заменён на AdvancedLogger
$hooks->on(Hook::BeforeSend, AdvancedLogger::class, name: 'logging');

// Удаление по имени
$hooks->remove(Hook::BeforeSend, name: 'logging');
```

**Для атрибутов:** имя по умолчанию = FQCN класса handler.

```php
#[BeforeSend(LogHandler::class)]  // name = 'App\Hooks\LogHandler'
#[BeforeSend(LogHandler::class, name: 'custom')]  // явное имя
```

---

## EarlyReturnException

Hook может прервать выполнение и вернуть результат без HTTP вызова:

```php
class CacheHook implements BeforeSendHookInterface
{
    public function handle(PipelineContext $context): void
    {
        $cacheKey = $this->getCacheKey($context->request);
        
        if ($cached = cache()->get($cacheKey)) {
            throw new EarlyReturnException(data: $cached);
        }
    }
}
```

SDK перехватывает исключение и строит `ExecutionResult` из данных.

См. [Исключения](./exceptions.md#earlyreturnexception--прерывание-без-http)

## Когда что использовать

| Ситуация | Подход |
|----------|--------|
| Логирование, трейсинг — для всех | Централизованно, глобально |
| Unwrap обёртки API — для DTO | Централизованно, forDto |
| Подпись для группы запросов | Централизованно, for |
| Специфичная логика одного запроса | Атрибут на запросе |
| Логика внутри класса запроса | Метод в классе |

## Пример в Laravel

```php
// AppServiceProvider или отдельный провайдер
public function register(): void
{
    $this->app->singleton(HookRegistry::class, function ($app) {
        $hooks = new HookRegistry(
            resolver: fn(string $class) => $app->make($class),
        );
        
        $hooks->on(Hook::BeforeSend, AddTraceHeader::class);
        $hooks->on(Hook::AfterResponse, LogApiResponse::class);
        $hooks->on(Hook::BeforeHydrate, UnwrapResponse::class, forDto: ApiWrapper::class);
        
        return $hooks;
    });
    
    $this->app->singleton(ApiClient::class, function ($app) {
        return new ApiClient(
            config: new ClientConfig(
                baseUrl: config('api.url'),
                cache: $app->make(CacheInterface::class),
            ),
            hooks: $app->make(HookRegistry::class),
        );
    });
}
```
