# Request Pipeline

Механизмы композиции и управления запросами.

## Типы запросов

| Тип | Интерфейс | Endpoint | Описание |
|-----|-----------|----------|----------|
| Простой | — | ✅ Есть | Обычный запрос к API |
| С зависимостями | `DependsOnRequestInterface` | ✅ Есть | Сначала зависимости, потом свой запрос |
| Композитный | `CompositeRequestInterface` | ❌ Нет | Виртуальный, только агрегация вложенных |

---

## Композитные запросы (Composite)

Виртуальный запрос без собственного endpoint. Объединяет несколько запросов в один логический вызов.

### Базовое использование

```php
class GetPersonFullData extends AbstractRequest implements CompositeRequestInterface
{
    public function __construct(
        public string $uuid,  // передаётся во все вложенные
    ) {}
    
    public function requests(): RequestCollection
    {
        return RequestCollection::make([
            GetBankruptcy::class,
            GetCourts::class,
            GetFssp::class,
        ]);
    }
}
```

**Использование:**
```php
$result = $client->person()->fullData($uuid)->send();

$result->data;      // PersonFullData DTO
$result->status;    // SUCCESS | PARTIAL | FAILED
$result->errors;    // Ошибки (если есть)
```

### Кастомная агрегация

```php
class GetPersonAnalytics extends AbstractRequest implements CompositeRequestInterface
{
    public function requests(): RequestCollection { ... }
    
    public function aggregate(ResultCollection $results, PipelineContext $ctx): array
    {
        return [
            'risk_score' => $this->calculateRisk($results),
            'has_issues' => $results->hasErrors(),
        ];
    }
}
```

---

## Запросы с зависимостями (DependsOn)

Запрос с собственным endpoint, которому нужны результаты других запросов перед выполнением.

### Базовое использование

```php
#[Get('/person/{uuid}/report')]
class GetPersonReport extends AbstractRequest implements DependsOnRequestInterface
{
    public ?string $uuid = null;
    public string $lastName;
    public string $firstName;
    
    public function dependencies(): RequestCollection
    {
        return RequestCollection::make([
            // Класс — параметры из $this
            GetToken::class,
            
            // Инстанс — с явными параметрами
            new GetUuid(
                lastName: $this->lastName,
                firstName: $this->firstName,
            ),
            
            // Callable
            fn() => new GetPermissions(scope: 'read'),
        ]);
    }
    
    public function processDependencies(ResultCollection $results, PipelineContext $ctx): void
    {
        $this->uuid = $results->get(GetUuid::class)->data->uuid;
        $this->withHeader('Authorization', 'Bearer ' . $results->get(GetToken::class)->data->token);
    }
}
```

---

## ResultCollection

Типизированная коллекция результатов дочерних/зависимых запросов.
Доступ по классу запроса через get(class); по индексу — через all()[index]. Есть помощники для проверки ошибок и фильтрации.

---

## Конфигурация выполнения

Атрибут `#[Execution]` управляет режимом выполнения вложенных запросов (Composite). Для DependsOn режим всегда последовательный.

```php
#[Execution(mode: ExecutionMode::Parallel, failStrategy: FailStrategy::Partial)]
class GetPersonFullData extends AbstractRequest implements CompositeRequestInterface
{
    // ...
}
```

| Параметр | Значения | Default |
|----------|----------|---------|
| `ExecutionMode` | `Sequential`, `Parallel` | Sequential |
| `FailStrategy` | `FailAll`, `Partial`, `IgnoreErrors` | FailAll |

**Применимость:**
- `CompositeRequestInterface` — да
- `DependsOnRequestInterface` — только `failStrategy`, `mode` игнорируется
- Простой запрос — игнорируется

---

## Хуки жизненного цикла

Каждый запрос (простой, вложенный, с зависимостями) проходит lifecycle:

```
beforeSend → HTTP → afterResponse → beforeHydrate → hydrate → afterHydrate
```

### Методы в классе

Контекст доступен через `$this` — параметры не нужны:

```php
class GetOrder extends AbstractRequest
{
    protected function beforeSend(): void
    {
        if ($this->isRoot()) {
            // Дополнительная логика для корневого запроса
        }
    }
    
    protected function afterResponse(PipelineContext $context): void { ... }
    protected function beforeHydrate(PipelineContext $context, array $data): array { ... }
    protected function afterHydrate(PipelineContext $context): void { ... }
}
```

**Доступ к данным в методах класса:**
- `$context->request` — текущий запрос
- `$context->preparedRequest` — сериализованный запрос
- `$context->response` — ответ API
- `$context->response->json()` — исходные данные для beforeHydrate
- `$data` — текущие данные после hook-обработчиков
- `$context->dto` — результат гидрации (в afterHydrate)
- `$context->role` — RequestRole enum
- `$context->traceId` — ID трассировки

### Переиспользуемые хуки (атрибуты)

Внешние классы получают request и context параметрами:

```php
#[BeforeSend(AddTimestampHeader::class)]
class GetOrder extends AbstractRequest { ... }

class AddTimestampHeader implements BeforeSendHookInterface
{
    public function handle(PipelineContext $context): void
    {
        if ($context->role === RequestRole::Root) {
            $context->preparedRequest = $context->preparedRequest->withHeader(
                'X-Timestamp',
                (string) time(),
            );
        }
    }
}
```

**Порядок выполнения:** Централизованные глобальные → Централизованные по типу → Атрибуты → Методы класса

См. [Хуки и HookRegistry](./hooks.md)

---

## PipelineContext

Контекст выполнения, доступный во всех handlers и hooks. Мутабельный для
промежуточных данных pipeline.

```php
class PipelineContext
{
    public function __construct(
        public readonly RequestInterface $request,
        public readonly ClientConfig $config,
        public readonly string $traceId,
        public readonly RequestRole $role = RequestRole::Root,
        public readonly ?PipelineContext $parent = null,
        // Мутабельные — меняются в процессе pipeline
        public ?PreparedRequest $preparedRequest = null,
        public ?ProviderResponse $response = null,
        public ?object $dto = null,
    ) {}
    
    /**
     * Создать дочерний контекст (для nested/composite)
     */
    public function child(RequestInterface $request, RequestRole $role): self;
}

enum RequestRole: string
{
    case Root = 'root';           // Корневой запрос
    case Nested = 'nested';       // Вложенный в Composite
    case Dependency = 'dependency'; // Зависимость в DependsOn
}
```

**Всегда доступен** — даже для простого запроса. Для методов класса — через `$this->getContext()` и helper-методы.

---

## Результат

```php
$result = $client->person()->fullData($uuid)->send();

// Статус (enum)
$result->status;        // SUCCESS | PARTIAL | FAILED

// Проверки состояния
$result->isSuccess();   // Полный успех (всё получено)
$result->isPartial();   // Частичный (есть data и errors)
$result->isFailed();    // Полный провал (нет data)

// Практичные проверки
$result->hasData();     // Можно работать с результатом
$result->hasErrors();   // Есть проблемы

// Данные
$result->data;          // DTO или partial data
$result->errors;        // ErrorCollection
```

---

## Структура ошибок

Ошибки разделены на два уровня:

**SDK-уровень (RequestError):**
- Код ошибки SDK (Timeout, ServerError, AuthError, etc.)
- Сообщение
- Вложенные ошибки (для composite/dependencies)

**Provider-уровень (ProviderResponse):**
- HTTP-статус
- Заголовки
- Оригинальный ответ API

```php
$error = $result->errors->first();

$error->code;                    // ErrorCode enum (SDK)
$error->message;                 // Описание
$error->response->httpStatus;    // HTTP код от API
$error->response->payload;       // Тело ответа от API
$error->nested;                  // Вложенные ошибки (если есть)
```

---

## Debug mode

В debug mode доступна полная информация о выполнении:

```php
$result->debug->preparedRequest;   // PreparedRequest (что отправили)
$result->debug->response;          // ProviderResponse (что получили)
$result->debug->duration;          // Время выполнения (ms)
$result->debug->nested;            // Debug вложенных запросов
```

---

## Связь с Batch

Composite и [Batch](./batch.md) используют общий `BatchExecutor` для выполнения коллекции запросов:

| Тип | Определение | Результат |
|-----|-------------|-----------|
| Composite | Compile-time (интерфейс) | `ExecutionResult` с агрегированным DTO |
| Batch | Runtime (метод клиента) | `BatchResult` (extends ExecutionResult) |

Composite вызывает `aggregate()` для объединения результатов в один DTO. Batch возвращает `BatchResult` с вложенными результатами.

**Единая иерархия:** все результаты наследуются от `ExecutionResult`, реализуют `ResultInterface`.

См. [Batch (Runtime)](./batch.md)
