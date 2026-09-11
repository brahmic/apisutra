## Ревизия и фиксация

### Этап 1.
- Тут нужно перечислить все ключевые компоненты и узлы пакета
- Выполнить анализ и ислледование кода каждого отдельного элемента, определить преречень необходимых тестов
- Для каждого элемента в списке создать отдельный файл и записать туда перечнь тестов, которые надо будет реализовать
- Все тесты нужно сгруппировать (предложи способ группировки)
- Все тесты будут в рамках этого пакета

- PLAN-TESTS-RECOMMENDED-CHECKS.md  - тесты из этого файла нужно будет рапределить в соответствии с новыми файлами 
### Этап 2.

- нужно заранее проработать, какме материалы, фикстуры и пр. нужны, чтобы обеспечить маскимально качественное тестирование пакета
- нужно заранее придумать юзкейсы, ситуации 

### Принцип группировки (best practice)
- Группируем **по компонентам**, а внутри каждого файла делим на:
  - Scope / Invariants
  - Unit tests
  - Integration tests
  - Edge cases
  - Fixtures/Mocks
  - Priority (P0/P1/P2)

### Минимальный набор унификации (обязательно)
- `TestClientFactory` — единая сборка клиента с `Environment`, `MockTransport`, cache, rateLimit.
- `MockResponseBuilder/Sequence` — короткие хелперы для 500→200, 401→refresh→200, 429→Retry‑After.
- `Request/DTO stubs` — базовые заглушки для pagination/cache/retry/attributes.
- `Assert helpers` — `assertContextHasOptions`, `assertCacheKeyContains`, `assertResultStatus`.
- `TestTrait` — общий setUp/tearDown (`MockClient::destroyGlobal()`, `RequestSpecResolver::clearCache()`).

### Список файлов (ключевые компоненты)
1) `00-client-config.md` — AbstractClient, ClientConfig и config‑VO  
2) `01-request-core.md` — AbstractRequest, RequestSpec/Options/Execution, Resource, Factory  
3) `02-pipeline-core.md` — Pipeline, Context/Factory, Flow, Preparer/Hydrator/ResultFactory  
4) `03-cache-idempotency.md` — CacheManager, cache key, без кэша  
5) `04-retry-rate-limit-delay.md` — RetrySender/Resolver, RateLimitApplier, DelayApplier, RateLimiter  
6) `05-pagination.md` — Paginator, PaginationOptions/Meta, AbstractPaginatedRequest  
7) `06-executors.md` — Batch/Pool/Composite/DependsOn + стратегии  
8) `07-attributes.md` — AttributeRegistry/Cache/Context/StageProcessor  
9) `08-serialization-hydration-casts.md` — Serializer/Hydrator/CastRegistry  
10) `09-transport-testing.md` — Transport, MockClient/MockTransport/RecordingTransport  
11) `10-auth.md` — AuthHandler, Authenticators, CacheAware  
12) `11-results-collections.md` — ExecutionResult, Batch/Pool/Paginated, ResultCollection  
13) `12-files-archive.md` — FileInput/Base64File/FileResponse/ArchiveConfig  
14) `13-extensions-hooks.md` — ExtensionRegistry, HookRegistry/HookRunner  
15) `14-provider-emulation.md` — эмуляция IIDX/Kontur (sync/async + enums)  
16) `90-fixtures-and-scenarios.md` — общие фикстуры, материалы, сквозные кейсы

### Распределение из PLAN-TESTS-RECOMMENDED-CHECKS
- Пагинация (1.1/1.2) → `05-pagination.md`
- Цепочка withCache()->withPage() (2.2) → `03-cache-idempotency.md` + `05-pagination.md`
- Rate limit key (2.3) → `04-retry-rate-limit-delay.md`

### Этап 2 (фиксируем заранее)
- Отдельный файл `90-fixtures-and-scenarios.md` для материалов, фикстур и сквозных кейсов.

все обсуждаем
