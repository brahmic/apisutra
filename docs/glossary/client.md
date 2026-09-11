# Клиент

## AbstractClient
Базовый класс для API-клиентов. Принимает ClientConfig и TransportInterface, а также HookRegistry, AttributeRegistry и ExtensionRegistry. Основные операции: send(), sendAsync(), response(), batch(), pool(), тестовые fake/assert. Управляет расширениями через registerExtension()/getExtension(). Конкретные клиенты наследуются и определяют доступные ресурсы.

## ClientInterface
Интерфейс для клиентов SDK. Методы: send(RequestInterface, SendMode $mode = SendMode::Sync): ResultHandle, sendAsync(RequestInterface): ResultHandle, response(ResolvedResultInterface): ClientResponse, getConfig(): ClientConfig, continuation(): ContinuationService. Позволяет типизировать зависимость от клиента без привязки к конкретной реализации.

## ClientConfig
Readonly class конфигурации клиента. Группирует настройки в VO: retry (RetryConfig), cacheConfig (CacheConfig), rateLimit (RateLimitConfig), pool (PoolConfig), archive (ArchiveConfig), paginationRule (PaginationRule), dtoSerializationProfile (body DTO contract). Простые значения в корне: baseUrl, auth, authRetryOn401, authRetryAttempts, timeout, connectTimeout, delay, debug, throwOnErrors, environment, namingStrategy, idempotencyHeader, casts, queryArrayFormat, serializeNulls, requestPartsEnumOutput, requestPartsStrictEnums, extensions, logger/cache, resolvedResultFactory, errorMapper, responseFactory, containerProvider, continuationTokenExtractor, defaultContinuationMode, defaultPollRequest, continuationModeApplicator. Передаётся в конструктор клиента и задаёт defaults для всех запросов и request-level поведения.

## delay
Параметр ClientConfig. Фиксированная задержка (мс) перед каждым запросом. Для "вежливого" обращения к API. Отличается от rate‑limit — это просто пауза.

## throwOnErrors
Параметр ClientConfig. Если true — автоматически выбрасывает исключение при неуспешных запросах. По умолчанию false — используется Result‑паттерн.

## ArchiveConfig
VO конфигурации архивов. Параметры: driver (`native`|`spatie`|`auto`), tempDir, maxSize, tempProvider. Используется в `ClientConfig::archive`.

## RetryConfig
VO конфигурации retry. Параметры: attempts, baseDelay, maxDelay, backoff (BackoffStrategy), jitter (bool), retryOn (array<int> HTTP статусов), retryExceptions (array<string>). Группирует все настройки повторных попыток.

## CacheConfig
VO конфигурации кеширования. Параметры: store (PSR-16), ttl, prefix, mode (CacheMode). Группирует настройки кеша.

## CacheMode
Enum режима кеша. Значения: Enabled, Disabled, ReadOnly, WriteOnly. Управляет чтением/записью кеша.

## RateLimitConfig
VO конфигурации rate‑limit. Параметры: limit, period, behavior (RateLimitBehavior), store (PSR-16 для распределённых лимитов). Группирует настройки throttling.

## BackoffStrategy
Namespace: `Brahmic\ApiSutra\Enums\RateLimiting\BackoffStrategy`.
Enum стратегии увеличения задержки при retry. Constant — фиксированная. Linear — линейное увеличение. Exponential — экспоненциальное (delay * 2^attempt).

## PoolConfig
VO конфигурации пула запросов (pool). Параметры: concurrency (макс. параллельных), stopOnFailure. Для массовых операций.

## PoolExecutor
Исполнитель pool‑режима. Принимает набор запросов и concurrency‑параметры, запускает их с ограничением параллельности и возвращает PoolResult. Concurrency вычисляется один раз при старте выполнения. Поддерживает `withRole()`, `withResponseHandler()`, `withExceptionHandler()` (иммутабельные настройки, возвращают новый экземпляр).

## ConcurrencyResolverInterface
Контракт для определения стартового значения concurrency в pool‑режиме. Может быть callable или класс‑резолвер.

## NamingStrategy
Namespace: `Brahmic\ApiSutra\Enums\Configuration\NamingStrategy`.
Enum стратегии именования полей. None — как есть (default), SnakeCase — camelCase ↔ snake_case. Настраивается в ClientConfig, применяется при сериализации запросов и гидрации DTO.

## requestPartsEnumOutput
Параметр ClientConfig. Определяет формат enum для query/header/path. `Object` не допускается для request parts.

## requestPartsStrictEnums
Параметр ClientConfig. Включает строгую проверку `title()` для request-level enum serialization, если выбран режим, которому нужен title.

## DtoSerializationProfileInterface
Контракт DX DTO serialization profile. Может передаваться в `ClientConfig`, но не как второй независимый источник истины, а как ссылка на тот же SDK-level DX contract, который использует `toArray()`.

## wireBodySerializationPolicy
Transport-level body serialization policy в `ClientConfig`.
Используется для outbound body по умолчанию и может отличаться от `DtoSerializationProfile`.

## EnumOutput
Namespace: `Brahmic\ApiSutra\Enums\Serialization\EnumOutput`.
Enum режимов вывода enum: Value, Name, Object, TitleValueString. TitleValueString формирует строку `title|value`.

## requestDateTime
Request-level date-time serialization policy в `ClientConfig`.
Тип: `DateTimeSerializationPolicy`.
Применяется к `query/header/path` и request body fields, которые не сериализуются как DTO body contract.

## DtoHydrationProfileInterface
Контракт SDK-level DTO hydration policy. Задаёт naming fallback, date-time hydration rules и stable casts для `DTO::from()` и pipeline hydration.

## DtoHydrate
Class-level partial override поверх `DtoHydrationProfile`. Используется для точечной настройки hydration semantics на уровне конкретного DTO.

## DateTimeInvalidBehavior
Namespace: `Brahmic\ApiSutra\Enums\Serialization\DateTimeInvalidBehavior`.
Enum поведения при ошибке парсинга дат: Throw (исключение) или Null (вернуть null).

## Environment
Namespace: `Brahmic\ApiSutra\Enums\Configuration\Environment`.
Enum окружения приложения. Значения: Local, Testing, Staging, Production. Влияет на поведение SDK: в Local/Testing отключен AttributeMetadataCache и включён verbose logging; в Production — cache включён, logging минимальный.

## ErrorCode
Namespace: `Brahmic\ApiSutra\Enums\Errors\ErrorCode`.
Enum кодов ошибок SDK. Категории: Transport (ConnectionFailed, Timeout, DnsError), HTTP 4xx (Unauthorized, Forbidden, NotFound, ValidationFailed, RateLimited), HTTP 5xx (ServerError, BadGateway, ServiceUnavailable, GatewayTimeout), Internal (ConfigurationError, HydrationError, SerializationError, ExtensionError).

## Auto‑discovery клиентов
Механизм автоматического поиска и регистрации namespace запросов клиента. Нужен для простого DX: запрос, созданный через DI, сам находит своего клиента без ручного перечисления namespace. Алгоритм: root‑scan по классам → fallback‑конвенции → кеш → регистрация в ClientRegistry.

## ClientRegistry
Namespace: `Brahmic\ApiSutra\Resolver\ClientRegistry`.
Реестр соответствий `namespace запроса → client`. Использует самое длинное совпадение namespace для корректного разрешения вложенных структур. Гарантирует ownership: запрос всегда отправляется через «своего» клиента.

## ClientResolverInterface
Namespace: `Brahmic\ApiSutra\Contracts\Core\ClientResolverInterface`.
Контракт резолвера клиента. Методы: resolve(RequestInterface): ClientInterface, assertOwnership(ClientInterface, RequestInterface): void. Реализация должна разворачивать execution‑обёртки и работать с исходным запросом.

## ClientResolver
Namespace: `Brahmic\ApiSutra\Resolver\ClientResolver`.
Реализация резолвера, использующая ClientRegistry. Учитывает RequestExecutionInterface и разрешает клиента по исходному запросу. Используется для защиты от «чужих» запросов.

## DiscoveryOptions
Namespace: `Brahmic\ApiSutra\Resolver\DiscoveryOptions`.
VO настроек auto‑discovery. Параметры: cacheMode (DiscoveryCacheMode), cacheTtl, cacheKeyVersion. В auto‑режиме кеш включается в Production/Staging и выключается в Local/Testing.

## DiscoveryCacheMode
Namespace: `Brahmic\ApiSutra\Enums\Discovery\DiscoveryCacheMode`.
Enum режима кеша discovery: Auto, ForceOn, ForceOff. Управляет включением кеша независимо от окружения.

## ClientDiscoveryService
Namespace: `Brahmic\ApiSutra\Resolver\ClientDiscoveryService`.
Оркестратор auto‑discovery. Делает детекцию namespace, кеширует результат, регистрирует клиента в ClientRegistry. Ключ кеша стабилизируется через класс клиента + cacheKeyVersion + checksum Composer.

## ClientDiscoveryCache
Namespace: `Brahmic\ApiSutra\Resolver\ClientDiscoveryCache`.
Кеш результатов discovery. Использует PSR‑16 стор, если он передан; иначе — in‑memory кеш процесса. Префикс ключей: `apisutra.discovery.`.

## RequestNamespaceDetector
Namespace: `Brahmic\ApiSutra\Resolver\RequestNamespaceDetector`.
Определяет namespace запросов клиента. Root‑namespace выводится из класса клиента (первые два сегмента). Если root‑scan не дал результатов — используется fallback‑конвенция (`Root\\Requests`, `Root\\Resources`).

## RequestScanner
Namespace: `Brahmic\ApiSutra\Resolver\RequestScanner`.
Сканирует классы запросов. Предпочитает classmap Composer (быстро); при отсутствии — обходит PSR‑4 директории. Фильтрует классы по `extends AbstractRequest`. Важно: `class_exists` может триггерить autoload и быть дорогим в больших проектах.

## ClassMapProvider
Namespace: `Brahmic\ApiSutra\Resolver\ClassMapProvider`.
Достаёт classmap и PSR‑4 префиксы из Composer ClassLoader. Используется RequestScanner для оптимального способа поиска классов.
