# План восполнения пробелов документации ApiSutra

## Цель
Полностью закрыть функциональные пробелы относительно кода и старой
документации (`.temp/release_docs/features`) без копирования устаревших частей.
Источник истины — код. Старые тексты используем как список тем и идей.

## Принципы
- **Сначала код, потом текст**: каждую деталь подтверждаем в `src/*`.
- **Единый источник истины**: подробности живут в одном месте, остальные — ссылки.
- **Guides = “как сделать”**, Technical = обзор/потоки, Glossary = термины/сущности.
- **Стабильная навигация**: после правок обновить все оглавления и ссылки.

---

## Карта пробелов → куда вшить (с проверкой кода)

### 1) Ресурсы и навигация ✅
**Проверить код:** `src/Core/AbstractResource.php`  
**Добавить:**
- Новый гайд `docs/guides/resources.md`: паттерн `Client → Resource → Request`,
  `resource()` и `request()`, привязка клиента.
- Обновить `docs/guides/README.md` (ссылка) и `docs/glossary/requests.md`
  (расширить описание AbstractResource).

### 1a) ClientResolver и auto‑discovery ✅
**Проверить код:**  
`src/Resolver/ClientRegistry.php`, `src/Resolver/ClientResolver.php`,  
`src/Resolver/ClientDiscoveryService.php`, `src/Resolver/DiscoveryOptions.php`,  
`src/Resolver/RequestNamespaceDetector.php`, `src/Resolver/RequestScanner.php`
**Добавить:**
- Новый гайд `docs/guides/client-discovery.md`:
  - как работает `ClientRegistry` и matching по namespace,
  - `ClientResolverInterface` и auto‑resolve в `AbstractRequest`,
  - `ClientDiscoveryService::registerAuto()` и кеш discovery,
  - `DiscoveryOptions` и `DiscoveryCacheMode`.
- Ссылки из `docs/technical/architecture.md` и `docs/guides/requests.md`.

### 2) Request Pipeline: Composite и DependsOn ✅
**Проверить код:**  
`src/Contracts/Interfaces/Core/CompositeRequestInterface.php`  
`src/Contracts/Interfaces/Core/DependsOnRequestInterface.php`  
`src/Execution/BatchExecutor.php`, `src/Execution/CompositeExecutor.php`,
`src/Execution/DependsOnExecutor.php`
**Добавить:**
- Новый гайд `docs/guides/request-pipeline.md`:
  - что такое Composite/DependsOn,
  - `requests()/dependencies()/processDependencies()/aggregate()`,
  - `ExecutionMode` и `FailStrategy`,
  - порядок выполнения и роль `RequestRole`.
- Обновить `docs/technical/pipeline.md` (ссылка на гайд).

### 3) Batch (runtime) ✅
**Проверить код:** `src/Execution/BatchExecutor.php`, `src/Config/BatchConfig.php`,
`src/Result/BatchResult.php`, `src/VO/Metadata/BatchMeta.php`
**Добавить:**
- Новый гайд `docs/guides/batch.md`: конфигурация, результаты, отличие от Composite.
- Обновить `docs/glossary/collections.md` и `docs/guides/README.md`.

### 3a) Async‑API и ResultHandle ✅
**Проверить код:**  
`src/Request/RequestExecution.php`, `src/Result/ResultHandle.php`,  
`src/Execution/BatchExecutor.php`, `src/Execution/PoolExecutor.php`
**Добавить:**
- В `docs/guides/requests.md`:
  - `sendAsync()` возвращает `ResultHandle`, а не `Promise`,
  - `rawAsync()/resolvedAsync()` и когда их использовать,
  - какие исключения возможны только в runtime,
  - поведение `sendAsync()` при пагинации (без реальной async‑параллельности).
- В `docs/guides/batch.md` и `docs/guides/client-config/pool.md`:
  - `batch()->sendAsync()` и `pool()->sendAsync()` (тип промиса/результата).

### 3b) SleeperInterface и non‑blocking ✅
**Проверить код:**  
`src/Contracts/Interfaces/Timing/SleeperInterface.php`, `src/Timing/SystemSleeper.php`,  
`src/Pipeline/Transport/RetrySender.php`, `src/Pipeline/Auth/AuthHandler.php`
**Добавить:**
- В `docs/guides/retries-rate-limit.md` и `docs/guides/auth.md`:
  - где используется sleeper (delay/retry/refresh lock),
  - рекомендация для event‑loop: подмена на non‑blocking sleeper.

### 4) Pool (массовое выполнение) ✅
**Проверить код:** `src/Execution/PoolExecutor.php`, `src/Config/PoolConfig.php`,
`src/Contracts/Interfaces/Concurrency/ConcurrencyResolverInterface.php`
**Добавить:**
- Расширить `docs/guides/client-config/pool.md` (ограничения, handlers, stopOnFailure,
  только `RequestInterface`, concurrency фиксируется на старте пула).
- Добавить раздел в `docs/guides/batch.md` “Pool vs Batch”.

### 5) Логирование, Audit и TraceId ✅
**Проверить код:**  
`src/Pipeline/Diagnostics/AuditLogger.php`,  
`src/VO/Audit/PipelineEvent.php`, `src/VO/Audit/DebugInfo.php`,  
`src/Pipeline/Preparation/RequestPreparer.php`,  
`src/Core/AbstractClient.php`, `src/Request/RequestOptions.php`
**Добавить:**
- Новый гайд `docs/guides/logging.md`:
  - `traceId` (client/request/runtime),
  - audit‑лог (`PipelineEvent`, `PipelineStage`),
  - debug‑payload и затраты.
- Обновить `docs/technical/pipeline.md` и `docs/glossary/pipeline.md`.

### 5b) Debug/Environment и метаданные ✅
**Проверить код:**  
`src/Config/ClientConfig.php`, `src/Enums/Configuration/Environment.php`,  
`src/Attributes/AttributeMetadataCache.php`, `src/Core/AbstractClient.php`
**Добавить:**
- Новый гайд `docs/guides/client-config/observability.md`:
  - `logger`/`logLevel`,
  - `debug` и влияние на `ExecutionResult::debug`,
  - `environment` и влияние на метаданные/кеш.
- Ссылка из `docs/guides/logging.md` и `client-config/README.md`.

### 5a) Хуки и HookRegistry (централизованные) ✅
**Проверить код:**  
`src/Hooks/HookRegistry.php`,  
`src/Enums/Hooks/Hook.php`, `src/Enums/Hooks/HookPriority.php`,  
`src/Contracts/Interfaces/Hooks/*`, `src/Attributes/Hooks/*`,
`src/Traits/RequestHooksBridgeTrait.php`
**Добавить:**
- Новый гайд `docs/guides/hooks.md`:
  - HookRegistry и resolver (DI),
  - приоритеты, naming, remove/override,
  - порядок исполнения (глобальные → по типу → атрибуты → методы класса).
- В `docs/guides/attributes/hooks.md` оставить атрибуты и дать ссылку на гайд.

### 5c) Кастомные атрибуты и AttributeRegistry ✅
**Проверить код:**  
`src/Attributes/AttributeRegistry.php`, `src/Attributes/AttributeContext.php`,  
`src/Contracts/Interfaces/Attributes/*`, `src/Enums/Attributes/AttributeContextType.php`,  
`src/Pipeline/Attributes/StageProcessor.php`,  
`src/Extensions/ExtensionRegistry.php`, `src/Extensions/ExtensionContext.php`
**Добавить:**
- Расширить `docs/technical/attributes.md`:
  - `AttributeRegistry`, `AttributeContext`,
  - `AttributeHandlerInterface` vs `AttributeContextHandlerInterface`,
  - порядок обработки (класс → свойства, порядок объявления),
  - влияние `PipelineStage` и доступ к `PipelineContext`.
- В `docs/guides/attributes/README.md` дать ссылку на тех. раздел,
  а в `docs/guides/extensions.md` упомянуть регистрацию хендлеров атрибутов.

### 6) Сериализация запросов (convention + приоритеты) ✅
**Проверить код:**  
`src/Serialization/RequestPartsCollector.php`,  
`src/Serialization/Serializer.php`,  
`src/Request/RequestSpecResolver.php`
**Добавить:**
- Новый гайд `docs/guides/serialization.md`:
  - convention (path/query/body),
  - `serializeNulls`,
  - `QueryArrayFormat`,
  - `Body(nested)`,
  - `resolveEndpoint/resolveBaseUrl/withBaseUrl` приоритеты,
  - DTO‑сериализация тела: `#[To]`, dot‑paths, только публичные свойства, fallback toArray/JsonSerializable.
- В `docs/guides/requests.md` добавить краткий раздел + ссылка.

### 7) DTO‑гидрация и Nested‑параметры ✅
**Проверить код:**  
`src/Serialization/Hydrator.php`,  
`src/Attributes/DataTransfer/Nested.php`,  
`src/Contracts/Interfaces/DataTransfer/DefaultValueProviderInterface.php`,
`src/Enums/DataTransfer/ValueState.php`
**Добавить:**
- Расширить `docs/guides/dto.md`:
  - `Nested` параметры (`from/each/discriminator/map/type`),
  - `computed()` и PipelineContext,
  - `DefaultValue`/`DefaultValueProvider`.
- Ссылки на `docs/guides/attributes/data-transfer.md`.

### 8) Casts (приоритеты и встроенные) ✅
**Проверить код:** `src/Casts/*`, `src/Serialization/CastResolver.php`,
`src/Serialization/RequestPartsCollector.php`
**Добавить:**
- Новый гайд `docs/guides/casts.md`:
  - приоритет (attr → config → built‑in),
  - список встроенных кастов,
  - примеры для DTO и сериализации.
- Обновить `docs/guides/client-config/serialization.md` ссылкой.

### 8a) NamingStrategy (детали и рекомендации) ✅
**Проверить код:** `src/Enums/Configuration/NamingStrategy.php`,
`src/Serialization/NamingStrategyResolver.php`, `src/Serialization/Serializer.php`
**Добавить:**
- Новый гайд `docs/guides/naming-strategy.md`:
  - где применяется (request/response),
  - переопределение через `#[From]/#[Query]`,
  - практические рекомендации выбора.
- Ссылка из `docs/guides/serialization.md`.

### 9) Валидация (Request + DTO) ✅
**Проверить код:** `src/VO/Validation/Validator.php`, `src/Attributes/DataTransfer/Validate.php`,
`src/Attributes/DataTransfer/Label.php`, `src/Result/ExecutionResult.php`
**Добавить:**
- Новый гайд `docs/guides/validation.md`:
  - поток валидации,
  - `ValidationError` в `ExecutionResult`,
  - кастомные сообщения и `Label`.
- Ссылки из `docs/guides/requests.md` и `docs/guides/dto.md`.

### 10) Auth (built‑in, refresh, cache) ✅
**Проверить код:** `src/Auth/*`, `src/Pipeline/Auth/AuthHandler.php`,
`src/Contracts/Interfaces/Auth/*`
**Добавить:**
- Расширить `docs/guides/auth.md`:
  - built‑in аутентификаторы,
  - refresh flow,
  - `CacheAwareInterface`,
  - `authRetryOn401` и `authRetryAttempts`,
  - приоритеты `AuthScope/NoAuth/forceAuth`,
  - refresh‑lock и поведение при конкурентном refresh,
  - роль `AuthPolicyInterface`.

### 11) Cache (ключ, исключения, download/upload) ✅
**Проверить код:** `src/Pipeline/Cache/CacheManager.php`,
`src/Enums/Cache/CacheMode.php`
**Добавить:**
- Расширить `docs/guides/client-config/cache.md`:
  - ключ кеша,
  - режимы read/write‑only,
  - cache для download (opt‑in) и запрет для upload,
  - `clearCache()` и соответствие ключей,
  - `CacheConfig::prefix` и нюанс `cache` (CacheInterface vs CacheConfig).
- Добавить короткий раздел в `docs/guides/requests.md`.

### 12) Retry / Rate‑limit / Delay / Idempotency ✅
**Проверить код:**  
`src/Retry/RetryHandler.php`, `src/Pipeline/Transport/RetryDecisionMaker.php`,  
`src/Config/RetryConfig.php`, `src/Config/RateLimitConfig.php`,  
`src/Pipeline/Transport/RateLimitApplier.php`, `src/Attributes/Behavior/Idempotent.php`
**Добавить:**
- Расширить `docs/guides/retries-rate-limit.md`:
  - backoff/jitter,
  - `retryExceptions`, `totalTimeoutMs`,
  - `RateLimitBehavior`, key‑strategy и store,
  - `RetryableException` и `Retry-After`,
  - 401/refresh приоритеты.
- Дополнить `docs/guides/client-config/retry.md` и `rate-limit.md`.

### 13) Пагинация (полная спецификация) ✅
**Проверить код:**  
`src/Config/PaginationConfig.php`, `src/Pagination/Paginator.php`,
`src/Pagination/PaginationRule.php`, `src/Attributes/Behavior/Pagination.php`,
`src/Request/PaginationOptions.php`
**Добавить:**
- Расширить `docs/guides/pagination.md`:
  - `PaginationConfig` поля,
  - `extractMeta` приоритет,
  - `PaginationMeta`/`PaginationMetaResolverInterface`/`PaginationMetaOverrideInterface`,
  - `PaginationItemsContainerInterface` и `AbstractPaginationContainerDto`,
  - `PaginatedResult` (items/pages/meta),
  - `maxPages` guard,
  - cursor/offset‑based.
- Добавить детали в `docs/guides/client-config/pagination.md`.

### 14) Файлы и архивы ✅
**Проверить код:**  
`src/VO/Files/FileInput.php`, `src/VO/Files/Base64File.php`,
`src/VO/Files/FileResponse.php`, `src/Extensions/Archive/*`,
`src/Config/ArchiveConfig.php`
**Добавить:**
- Расширить `docs/guides/files.md`:
  - Base64 в response,
  - несколько файлов,
  - FileInput factories,
  - архивы и зависимости.
- Расширить `docs/guides/client-config/archive.md`.

### 15) Extensions (lifecycle, conflicts, override) ✅
**Проверить код:**  
`src/Extensions/ExtensionRegistry.php`, `src/Extensions/ExtensionContext.php`,
`src/Extensions/Archive/*`
**Добавить:**
- Новый гайд `docs/guides/extensions.md`:
  - `register` vs `boot`,
  - конфликт handlers и `override`,
  - `ExtensionDisabledException`.
- Ссылки из `docs/guides/client-config/extensions.md`.

### 16) Exceptions и Result‑паттерн ✅
**Проверить код:** `src/Result/ExecutionResult.php`, `src/Exceptions/*`,
`src/Pipeline/Error/ErrorPolicy.php`
**Добавить:**
- Новый гайд `docs/guides/errors.md`:
  - `ResultHandle`, `throw()`, `throwOnErrors`,
  - кастомизация `hasRequestFailed/shouldRetry/getRequestException`,
  - `ExecutionResult` (status, errors, nested),
  - типы исключений и когда их ждать,
  - control‑flow исключения (`EarlyReturnException`, `RetryableException`) и их эффект.
- Ссылки из `docs/technical/error-handling.md`.

### 16a) Response factories и маппинг ошибок ✅
**Проверить код:**  
`src/Result/ResolvedResultFactoryInterface.php`,  
`src/Response/ClientResponseFactoryInterface.php`,  
`src/Response/ClientErrorMapperInterface.php`,
`src/Response/ClientResponseFactory.php`, `src/Response/ClientErrorMapper.php`
**Добавить:**
- Расширить `docs/guides/client-config/responses-errors.md`:
  - зачем фабрики и мапперы,
  - типовые сценарии,
  - пример кастомного маппера.

### 17) Laravel‑интеграция ✅
**Проверить код:**  
`src/Laravel/RequestFactory.php`,  
`src/Contracts/Interfaces/Factory/RequestFactoryInterface.php`,  
`src/Laravel/SdkServiceProvider.php`
**Добавить:**
- Новый гайд `docs/guides/laravel.md`:
  - DI запросов,
  - RequestFactoryInterface,
  - RequestFactory resolvers и payload‑ключи,
  - ClientResponseAdapter,
  - default transport binding,
  - `ClientConfig::fromLaravel`.

### 17a) Transport и HTTP‑слой ✅
**Проверить код:**  
`src/Contracts/Interfaces/Core/TransportInterface.php`,  
`src/Transport/HttpTransport.php`,  
`src/Transport/MockTransport.php`, `src/Transport/RecordingTransport.php`
**Добавить:**
- Новый гайд `docs/guides/transport.md`:
  - обязанности TransportInterface,
  - PSR‑18/PSR‑17 зависимости,
  - взаимодействие с Mock/Recording transport.
- Ссылки из `docs/guides/quickstart.md` и `docs/guides/testing.md`.

### 18) Use‑cases и polling ✅
**Проверить код:** `docs/glossary/requests.md` (polling термин), `src/Request/*`
**Добавить:**
- Новый гайд `docs/guides/use-cases.md` (короткие сценарии):
  - простой GET/POST,
  - composite/batch,
  - pagination,
  - files,
  - long‑running/polling (как паттерн, не функция SDK).

### 18a) Тестирование (детали) ✅
**Проверить код:**  
`src/Testing/*`, `src/Transport/MockTransport.php`, `src/Transport/RecordingTransport.php`
**Добавить:**
- Расширить `docs/guides/testing.md`:
  - URL‑pattern и wildcard‑mocks,
  - последовательности `MockSequence`,
  - `MockConfig::throwOnMissingFixtures()`,
  - redaction через `Fixture`.

### 19) Навигация и оглавления ✅
**Обновить:**
- `docs/README.md`
- `docs/guides/README.md`
- `docs/technical/README.md` (если добавятся новые технические файлы)
- `docs/glossary/README.md` (добавить новые термины/ссылки)

### 20) Принципы и границы (архитектура) ✅
**Проверить код:** `docs/technical/architecture.md`, `docs/README.md`
**Добавить:**
- В `docs/technical/architecture.md` — короткий раздел “Принципы”:
  - stateless/immutable/declarative/convention‑over‑config,
  - return‑based errors.
- В `docs/README.md` — ссылка на принципы (если потребуется).

---

## Финальный проход качества
- Проверить отсутствие FQN в примерах (use только при первом упоминании в файле).
- Проверить терминологию: “провайдер = SDK‑клиент внешнего API”.
- Проверить актуальность ссылок и якорей.
- Убедиться, что примеры соответствуют текущим сигнатурам методов.
