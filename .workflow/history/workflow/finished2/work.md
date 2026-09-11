Ключевая последовательность (инварианты, которые нельзя нарушать)
1) override роли/traceId (если AbstractRequest) → PipelineContext → Started (audit + attributes) → лог старта.
2) Проверка CompositeRequestInterface / DependsOnRequestInterface до валидации и сериализации.
3) Валидация → ExecutionResult Failed (ValidationException) + audit Failed + лог + throwOnErrors.
4) Сериализация → applyRequestOverrides → BeforeSend attributes → handleAuthentication → BeforeSend hooks.
5) Обновлять $prepared после attributes/auth (DebugInfo строится по финальному PreparedRequest).
6) Кэш чтение → если попали в кэш, AfterResponse hooks; иначе sendWithRetry (delay, rate limit, retry handler, authRetryOn401).
7) Ошибка ответа → buildFailedResult + лог + throwOnErrors.
8) BeforeHydrate hooks → hydrateResponse → AfterHydrate attributes → AfterHydrate hooks → запись в кэш.
9) Audit Completed + DebugInfo (если debug) → meta для пагинации → ExecutionResult Success.
10) EarlyReturnException → hydrateResponse → Success (без AfterHydrate hooks и без cache).
11) Исключения: RequestException/ConfigurationException → ExecutionResult Failed + audit/log + throwOnErrors.

Тонкие моменты, которые надо сохранить 1:1
- PipelineContext — единый объект; для AbstractRequest вызывается setContext сразу после создания.
- Роль запроса может быть переопределена через AbstractRequest::getRoleOverride().
- resolveTraceId учитывает override у запроса, параметр, затем pipeline traceId.
- handleAuthentication() может вызвать execute() рекурсивно для refresh‑запроса.
- При 401 и authRetryOn401 refresh выполняется до общей retry‑логики.
- При включённом authRetryOn401 общий retry по 401 не применяется.
- hasRequestFailed учитывает overrides запроса/клиента, иначе статус >= 400.
- storeCache() пишет даже при overrideEnabled === false (логика спорная, но это фактическое поведение).
- Cache check не ограничен GET — ориентироваться только на CacheConfig/override, не добавлять новые условия.
- cached response проходит AfterResponse hooks, error‑check и гидрацию, как обычный ответ.
- buildCacheKey() использует xxh3 при наличии; порядок нормализации query важен.
- runHookStage() и runBeforeHydrate() имеют разный контракт обработки массивов.
- Порядок хуков: HookRegistry (глобальные → for request/DTO, как возвращает registry) → атрибуты (priority first/normal/last) → методы запроса.
- AttributeRegistry стадии: Started, BeforeSend, AfterHydrate; не объединять с hooks и не менять порядок.
- Приоритет retry: shouldRetry (request/client) → RetryableException → retryOn статусы.
- Retry повторяет только transport+afterResponse+shouldRetry; hydrate/afterHydrate выполняются один раз для финального ответа.
- hydrateResponse: Download → ExtensionRegistry handler → pagination itemsPath → returns.unwrap → hydrator.

Несоответствия доки/кода — зафиксировано, вернуться после рефакторинга
- Cache GET‑ограничение: в доке «только GET», в коде метод не проверяется. Сейчас действуем по коду.
- 401/refresh: refresh выполняется до общей retry‑логики; общий retry для 401 не применяется.
- refresh вызывается только когда authenticator.shouldRefresh() === true (это текущее поведение).

Рекомендуемая декомпозиция (с привязкой методов)
Ниже — безопасный разрез на компоненты без изменения логики. Имена примерные, можно адаптировать.
1) Pipeline (оркестратор, публичный API)
setTraceId(), execute(), executeAsync()
Создание контекста, try/catch верхнего уровня, связывание компонентов.

2) PipelinePreparation/RequestPreparer
resolveTraceId(), applyRequestOverrides(), generateIdempotencyKey()
Подготовка traceId/headers/idempotency, возвращает PreparedRequest.

3) PipelineAttributes/StageProcessor
processStage(Started/BeforeSend/AfterHydrate) обертка над AttributeRegistry.

4) PipelineExecution/CompositeFlow
executeComposite(), executeDependsOn(), buildCompositeResult()
Композитные/depends‑on сценарии, агрегация результатов.

5) PipelineAuth/AuthHandler
handleAuthentication(), refreshToken(), injectCache()
Важно: принимает коллбек/интерфейс для вызова execute() при refresh.

6) PipelineHooks/HookRunner
runHookStage(), resolveAttributeHooks(), runBeforeHydrate()
Сохранить порядок: registry hooks → attribute hooks → request hooks.

7) PipelineCache/CacheManager
clearCache(), checkCache(), storeCache(),
resolveCacheConfig(), buildCacheKey(), normalizeQueryForCache()
clearCache требует Serializer + RequestPreparer (или готовый PreparedRequest).

8) PipelineTransport/RetrySender
sendWithRetry(), resolveRetryConfig(), shouldRetry(),
isRetryException(), applyDelay(), applyRateLimit(),
resolveRateLimitConfig(), retryAfter()
Зависимости: HookRunner (AfterResponse), AuthHandler (authRetryOn401), ErrorPolicy.

9) PipelineError/ErrorPolicy
hasRequestFailed(), hasRequestFailedInternal(), getRequestExceptionInternal(),
mapException(), shouldRetryInternal() (сейчас не используется, но переносить вместе).

10) PipelineHydration/ResponseHydrator
hydrateResponse(), makeFileResponse(), getByPath()

11) ExecutionResult/ResultFactory
buildFailedResult()
Использует ErrorPolicy для exception mapping.

12) PipelineDiagnostics/AuditLogger
addAudit(), log(), shouldLog()
Важно: логирование использует config->logger, а не $logger из конструктора.

Минимально‑рисковая последовательность разрезания
Шаг 1: вынести чистые утилиты (normalizeQueryForCache, getByPath, retryAfter, generateIdempotencyKey).
Шаг 2: вынести CacheManager и HookRunner (простые границы и слабая связанность).
Шаг 3: вынести ResponseHydrator и ErrorPolicy (четкие контракты, минимум зависимостей).
Шаг 4: вынести RetrySender и RequestPreparer (связаны с контекстом/конфигом).
Шаг 5: вынести CompositeFlow и AuthHandler (самый рискованный участок из‑за рекурсии/контекстов).
Шаг 6: выделить StageProcessor (AttributeRegistry) и оставить Pipeline тонким фасадом.
