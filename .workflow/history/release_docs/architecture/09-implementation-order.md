# Implementation Order — Порядок реализации

DAG задач с зависимостями.

---

## Связанная документация

| Документ | Описание |
|----------|----------|
| [01-contracts](01-contracts.md) | Все интерфейсы SDK |
| [02-core-classes](02-core-classes.md) | Абстрактные классы |
| [03-pipeline](03-pipeline.md) | Pipeline и PipelineContext |
| [04-dto-system](04-dto-system.md) | Hydrator, Casts |
| [05-transport](05-transport.md) | Transport layer |
| [06-config](06-config.md) | ClientConfig и вложенные VO |
| [07-attributes](07-attributes.md) | Все атрибуты |
| [08-extension-points](08-extension-points.md) | Точки расширения |

**Features:**
[exceptions](../features/exceptions.md) ·
[hooks](../features/hooks.md) ·
[caching](../features/caching.md) ·
[authentication](../features/authentication.md) ·
[retry](../features/retry-rate-limiting.md) ·
[pagination](../features/pagination.md) ·
[files](../features/files.md) ·
[extensions](../features/extensions.md) ·
[testing](../features/testing.md) ·
[validation](../features/validation.md)

---

## Фазы реализации

```
Phase 1: Foundation (контракты, базовые классы)
    ↓
Phase 2: Core (pipeline, transport, serialization)
    ↓
Phase 3: Features (auth, cache, retry, hooks)
    ↓
Phase 4: Advanced (composite, batch, pool, pagination)
    ↓
Phase 5: Testing & Integration (mock, Laravel)
```

---

## Phase 1: Foundation

Базовые контракты и структуры данных.

📖 См. [01-contracts](01-contracts.md), [06-config](06-config.md), [exceptions](../features/exceptions.md)

| # | Задача | Зависит от | Файлы |
|---|--------|------------|-------|
| 1.1 | Enums | — | `Enums/*.php` |
| 1.2 | Value Objects | — | `VO/*.php` |
| 1.3 | Exceptions | — | `Exceptions/*.php` |
| 1.4 | Interfaces | 1.1, 1.2 | `Contracts/*.php` |
| 1.5 | Config VO | 1.1, 1.2 | `Config/*.php` |

### 1.1 Enums

```
HttpMethod, ResultStatus, RequestRole,
ExecutionMode, FailStrategy, Hook, HookPriority,
BackoffStrategy, RateLimitBehavior, FileFormat,
QueryArrayFormat, NamingStrategy, ErrorCode, Environment
```

### 1.2 Value Objects

```
PreparedRequest, ProviderResponse, PipelineContext,
RequestError, ValidationError,
PaginationMeta, BatchMeta, DebugInfo,
FileInput, FileResponse, Base64File
```

### 1.3 Exceptions

```
SdkException
├── ControlFlowException
│   ├── RetryableException
│   ├── EarlyReturnException
│   └── RefreshTokenException
├── ValidationException (локальная валидация)
├── ConnectionException
├── RequestException
│   ├── ClientException (4xx variants)
│   └── ServerException (5xx variants)
├── ConfigurationException
├── ExtensionException
│   ├── ExtensionConflictException
│   └── ExtensionDisabledException
└── TestingException
    ├── UnmockedRequestException
    └── MissingFixtureException
```

### 1.4 Interfaces

```
TransportInterface, ClientInterface, RequestInterface,
CompositeRequestInterface, DependsOnRequestInterface,
PaginableInterface, ResultInterface, ResultMeta,
DtoInterface, ResponseDtoInterface, ValidatableInterface,
AuthenticatorInterface, CacheAwareInterface,
CastInterface, HookInterface (variants),
AttributeHandlerInterface, ValidatorInterface,
ConcurrencyResolverInterface, RequestFactoryInterface,
ExtensionInterface, ResponseHandlerInterface
```

### 1.5 Config VO

```
ClientConfig, RetryConfig, RateLimitConfig,
CacheConfig, PoolConfig
```

---

## Phase 2: Core

Ядро выполнения запросов.

📖 См. [02-core-classes](02-core-classes.md), [03-pipeline](03-pipeline.md), [04-dto-system](04-dto-system.md), [05-transport](05-transport.md), [07-attributes](07-attributes.md)

| # | Задача | Зависит от | Файлы |
|---|--------|------------|-------|
| 2.1 | Attributes | 1.* | `Attributes/*.php` |
| 2.2 | Abstract DTO | 1.4 | `AbstractDto.php`, `AbstractResponseDto.php` |
| 2.3 | ValidatesAttributes | 1.4 | `Traits/ValidatesAttributes.php` |
| 2.4 | CastRegistry | 1.4, 2.1 | `Casts/CastRegistry.php` |
| 2.5 | Built-in Casts | 2.4 | `Casts/*.php` |
| 2.6 | Hydrator | 2.2, 2.4, 2.5 | `Hydrator.php` |
| 2.7 | Serializer | 2.1, 1.2 | `Serializer.php` |
| 2.8 | HttpTransport | 1.4, 1.2 | `Transport/HttpTransport.php` |
| 2.9 | AbstractRequest | 1.4, 2.1, 2.3 | `AbstractRequest.php` |
| 2.10 | ExecutionResult | 1.2, 1.4 | `Result/ExecutionResult.php` |
| 2.11 | Pipeline | 2.6, 2.7, 2.8, 2.10 | `Pipeline.php` |
| 2.12 | AbstractClient | 2.9, 2.11 | `AbstractClient.php` |
| 2.13 | AbstractResource | 2.12 | `AbstractResource.php` |

### 2.1 Attributes

```
HTTP: Get, Post, Put, Patch, Delete
Mapping: Path, Query, Body, Header, Ignore
DTO: From, Cast, Nested
Response: Returns
Validation: Validate, Label
Cache: Cache
Auth: NoAuth
Idempotency: Idempotent
Execution: Execution
Pagination: Pagination
Files: File, Download
Hooks: BeforeSend, AfterResponse, BeforeHydrate, AfterHydrate
Retry: Retry, Timeout
RateLimit: RateLimit
```

### 2.6 Hydrator

Порядок внутри:
1. Property reflection
2. From attribute processing
3. NamingStrategy
4. Cast lookup & apply
5. Nested recursion
6. Constructor invocation

### 2.11 Pipeline

Порядок внутри:
1. Validation step
2. Serialization step
3. Auth step (placeholder)
4. BeforeSend hooks (placeholder)
5. Cache check (placeholder)
6. Transport call
7. AfterResponse hooks (placeholder)
8. Error check
9. BeforeHydrate hooks (placeholder)
10. Hydration
11. AfterHydrate hooks (placeholder)
12. Cache store (placeholder)

---

## Phase 3: Features

Функциональные возможности.

📖 См. [hooks](../features/hooks.md), [authentication](../features/authentication.md), [caching](../features/caching.md), [retry](../features/retry-rate-limiting.md), [validation](../features/validation.md), [extensions](../features/extensions.md)

| # | Задача | Зависит от | Файлы |
|---|--------|------------|-------|
| 3.1 | Validator | 2.1 | `Validation/Validator.php` |
| 3.2 | HookRegistry | 1.4 | `Hooks/HookRegistry.php` |
| 3.3 | AttributeRegistry | 1.4 | `Attributes/AttributeRegistry.php` |
| 3.4 | AttributeMetadataCache | 3.3 | `Attributes/AttributeMetadataCache.php` |
| 3.5 | ExtensionRegistry | 1.4 | `Extensions/ExtensionRegistry.php` |
| 3.6 | ExtensionContext | 3.5 | `Extensions/ExtensionContext.php` |
| 3.7 | Auth system | 2.10 | `Auth/*.php` |
| 3.8 | Cache integration | 2.10, 1.5 | `Cache/*.php` |
| 3.9 | Retry logic | 2.11, 1.5 | `Retry/*.php` |
| 3.10 | Rate limiter | 2.11, 1.5 | `RateLimit/*.php` |
| 3.11 | Logging | 2.9 | `Logging/*.php` |

### 3.4 AttributeMetadataCache

```
Кеш результатов Reflection
- ClassMetadata (method, path, properties, casts, hooks)
- Включение зависит от Environment
- warmup() для production
```

### 3.5-3.6 Extensions

```
ExtensionRegistry - хранение per-client
ExtensionContext - регистрация компонентов
```

### 3.7 Auth system

```
AuthenticatorInterface implementations:
- BearerAuthenticator
- ApiKeyAuthenticator  
- BasicAuthenticator
- TokenAuthenticator (with refresh)
```

### 3.9 Retry logic

```
- BackoffCalculator
- RetryHandler (интеграция в Pipeline)
- shouldRetry() в AbstractClient
```

---

## Phase 4: Advanced

Продвинутые функции.

📖 См. [request-pipeline](../features/request-pipeline.md), [batch](../features/batch.md), [pagination](../features/pagination.md), [files](../features/files.md)

| # | Задача | Зависит от | Файлы |
|---|--------|------------|-------|
| 4.1 | Collections | 2.9 | `Collections/*.php` |
| 4.2 | BatchResult | 4.1 | `Result/BatchResult.php` |
| 4.3 | PaginatedResult | 2.9 | `Result/PaginatedResult.php` |
| 4.4 | PoolResult | 4.1 | `Result/PoolResult.php` |
| 4.5 | CompositeExecutor | 2.10, 4.1 | `Execution/CompositeExecutor.php` |
| 4.6 | DependsOnExecutor | 2.10, 4.1 | `Execution/DependsOnExecutor.php` |
| 4.7 | BatchExecutor | 2.10, 4.1, 4.2 | `Execution/BatchExecutor.php` |
| 4.8 | PoolExecutor | 2.10, 4.1, 4.4 | `Execution/PoolExecutor.php` |
| 4.9 | Paginator | 4.3, 2.8 | `Pagination/Paginator.php` |
| 4.10 | File handling | 2.6, 2.5 | `Files/*.php` |
| 4.11 | ArchiveExtension | 3.5, 4.10 | `Extensions/Archive/*.php` |
| 4.12 | Async support | 2.7, 2.9 | `Async/*.php` |

### 4.1 Collections

```
RequestCollection, ResultCollection, ErrorCollection
```

### 4.2-4.4 Result Types

```
BatchResult - результат batch()
PaginatedResult - результат paginate()
PoolResult - результат pool()
```

### 4.5-4.8 Executors

```
CompositeExecutor - для CompositeRequestInterface
DependsOnExecutor - для DependsOnRequestInterface
BatchExecutor - для batch()
PoolExecutor - для pool()
```

---

## Phase 5: Testing & Integration

📖 См. [testing](../features/testing.md), [08-extension-points](08-extension-points.md)

| # | Задача | Зависит от | Файлы |
|---|--------|------------|-------|
| 5.1 | MockTransport | 1.4, 1.2 | `Transport/MockTransport.php` |
| 5.2 | MockClient facade | 5.1 | `Testing/MockClient.php` |
| 5.3 | MockResponse | 1.2 | `Testing/MockResponse.php` |
| 5.4 | Fixture system | 5.1 | `Testing/Fixture.php` |
| 5.5 | RequestFactory | 2.8 | `Laravel/RequestFactory.php` |
| 5.6 | ServiceProvider | 5.* | `Laravel/SdkServiceProvider.php` |

---

## Граф зависимостей (упрощённый)

```
Enums ─┬─► Interfaces ─┬─► AbstractDto ─► Hydrator ───┐
       │               │                              │
VOs ───┤               ├─► Serializer ────────────────┤
       │               │                              │
Exceptions ────────────┤                              ▼
       │               │                     ExecutionResult
Config ────────────────┴─► HttpTransport ─────────────┤
                                                      │
                                                  Pipeline
                                                      │
                           AbstractRequest ◄──────────┤
                                 │                    │
                           AbstractClient ◄───────────┘
                                 │
                           AbstractResource
```

---

## Checklist реализации

### Phase 1 ✓
- [ ] Enums
- [ ] Value Objects (incl. PipelineContext)
- [ ] Exceptions
- [ ] Interfaces
- [ ] Config VOs

### Phase 2 ✓
- [ ] Attributes (все категории)
- [ ] AbstractDto / AbstractResponseDto
- [ ] ValidatesAttributes trait
- [ ] CastRegistry (with global())
- [ ] Built-in casts
- [ ] Hydrator (with default())
- [ ] Serializer
- [ ] HttpTransport
- [ ] AbstractRequest
- [ ] ExecutionResult
- [ ] Pipeline
- [ ] AbstractClient
- [ ] AbstractResource

### Phase 3 ✓
- [ ] Validator
- [ ] HookRegistry
- [ ] AttributeRegistry
- [ ] AttributeMetadataCache
- [ ] ExtensionRegistry
- [ ] ExtensionContext
- [ ] Auth system
- [ ] Cache integration
- [ ] Retry logic
- [ ] Rate limiter
- [ ] Logging

### Phase 4 ✓
- [ ] Collections (Request, Result, Error)
- [ ] BatchResult
- [ ] PaginatedResult
- [ ] PoolResult
- [ ] CompositeExecutor
- [ ] DependsOnExecutor
- [ ] BatchExecutor
- [ ] PoolExecutor
- [ ] Paginator
- [ ] File handling
- [ ] ArchiveExtension (built-in)
- [ ] Async support

### Phase 5 ✓
- [ ] MockTransport
- [ ] MockClient
- [ ] MockResponse
- [ ] Fixture system
- [ ] RequestFactory
- [ ] ServiceProvider

---

## Примечания

1. **Каждая фаза — рабочий код.** После Phase 2 можно делать простые запросы.

2. **Тесты пишутся параллельно.** Unit tests для каждого компонента.

3. **Phase 3-4 можно частично параллелить.** Auth и Cache независимы.

4. **Integration tests — в Phase 5.** После MockTransport.
