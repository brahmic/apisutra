# Покрытие возможностей документацией

Проверка реализации 027. Каждая строка задаёт владельца контракта, исходники и
наблюдаемые сценарии. Полные пути и SHA-256 находятся в [машинной карте](artifacts/feature-inventory.json).
Статус «сверено» означает редакционную проверку описания; результаты запусков
указываются отдельно и не выводятся из наличия файла теста.

Основная аудитория строк — пользователь пакета, включая автора SDK. Изменение
этих механизмов в src направляется через CONTRIBUTING/development.

## Порция A

| Тема и единственный владелец | Код | Сценарии |
| --- | --- | --- |
| [Клиент, config, with/null и явная сборка](../../../docs/reference/client/configuration.md) | ClientConfig, AbstractClient | ClientConfigTest, ContainerProviderRequestResolverTest |
| [Discovery, longest namespace, ownership и DI](../../../docs/reference/client/discovery.md) | ClientRegistry, ClientResolver, ClientDiscoveryService | ClientRegistryTest, DiscoveryFullFlowTest |
| [Ресурсы и привязка запросов](../../../docs/reference/client/resources.md) | AbstractResource | DiscoveryFullFlowTest |
| [Декларация и runtime-опции запроса](../../../docs/reference/request/declaration.md) | AbstractRequest, RequestExecution | SerializerRequestDefaultsTest, PipelineRequestOptionsProviderFallbackTest |
| [Подготовка частей запроса и RequestDefaults](../../../docs/reference/serialization/request-parts.md) | RequestPartsCollector | SerializerRequestDefaultsTest, SerializerTest |
| [URI, query, origin и готовые URL](../../../docs/reference/serialization/uri-query.md) | RequestUrlBuilder | UriPipelineTest, RequestUrlContractTest, ExternalUrlContractTest, ExternalUrlTransportTest |
| [JSON/body/BodyRoot и ошибка кодирования](../../../docs/reference/serialization/body.md) | Serializer | SerializerBodyRootTest, StrictJsonEncodingTest |
| [Транспорт, promise, capabilities и тело PreparedRequest](../../../docs/reference/execution/transport.md) | HttpTransport, PreparedRequest | HttpTransportTest, PreparedRequestBodyTest, PreparedBodyContractTest, TransportTimeoutIntegrationTest |
| [Результаты raw/resolved/dataOrFail и фабрики](../../../docs/reference/results/handles.md) | ResultHandle, ExecutionResult, ResolvedResult | SuccessfulResponseContractTest, ResultFactoryTest, ClientResponseFactoryTest |
| [RawResponse и разные MIME](../../../docs/reference/attributes/response.md) | RawResponse | RawResponseTest, SuccessfulResponseContractTest |
| [Ошибки, маппинг, partial и typed context](../../../docs/reference/results/errors.md) | ExecutionResult, ClientError | ErrorPolicyTest, ResolvedResultErrorContextTest, TraceErrorContextContractTest |

## Порция B

| Тема и единственный владелец | Код | Сценарии |
| --- | --- | --- |
| [Модели DTO, constructor-first и inherited properties](../../../docs/reference/dto/models.md) | Hydrator, AbstractDto | HydratorTest, HydrationCompatibilityTest |
| [HydrationRules, descriptors и конфликт деклараций](../../../docs/reference/dto/field-rules.md) | HydrationRules, DtoRules, FieldRule | ExternalHydrationConfigurationTest, ExternalHydrationRulesTest |
| [Профили, From/fallback и даты](../../../docs/reference/dto/profiles.md) | DtoHydrationProfileResolver, DateTimeHydrationPolicy | DateTimePolicyTest, HydrationCastSourcesTest |
| [Legacy/Strict, union и большие целые](../../../docs/reference/dto/scalars.md) | HydrationValueValidator, IntegerCast | ExternalHydrationScalarTest, BigIntegerIntegrationTest |
| [Missing, null, defaults и provider Present](../../../docs/reference/dto/defaults.md) | DefaultSpec, Hydrator | DefaultValueProviderContractTest, HydrationErrorContractTest |
| [Одиночный Nested, list, each, формы и required](../../../docs/reference/dto/shapes.md) | ValueShape, Hydrator | NestedObjectHydrationTest, ExternalHydrationRulesTest |
| [Discriminator Value/Key, KeepRaw/Skip/Error](../../../docs/reference/dto/variants.md) | ValueShape, Nested | NestedDiscriminatorModeTest, ExternalHydrationRulesTest |
| [Остаток источника, fallback, коллизия _extra, рекурсивная проекция](../../../docs/reference/dto/extras.md) | DtoRules, Hydrator | ExternalHydrationExtrasTest |
| [Вложенные casts/providers с текущим scope](../../../docs/reference/dto/scope.md) | HydrationScope | ExternalHydrationScopeTest |
| [Пути DTO/sourcePath и safe log](../../../docs/reference/dto/diagnostics.md) | HydrationException | ExternalHydrationOriginTest, HydrationErrorContractTest |
| [Типизированные коллекции и missing fallback](../../../docs/reference/dto/collections.md) | AbstractTypedCollection, AbstractCollection | TypedCollectionTest, CollectionIntegrationTest |
| [Изоляция object defaults/атрибутов, повторы и cache on/off](../../../docs/reference/dto/lifecycle.md) | AttributeMetadataCache, Hydrator, DtoSerializer | MetadataValueIsolationTest |
| [DX/wire, enum, даты и profile hierarchy](../../../docs/reference/serialization/dto-output.md) | DtoSerializer, SerializationValueResolver | DtoSerializerTest, DateTimePolicyTest |
| [Receiver в ручных/nested DTO, cast/opaque/query границы](../../../docs/reference/serialization/receiver-output.md) | Serializer, DtoSerializer | ExternalHydrationWireTest |
| [Источники cast registry vs hydration/profile и JsonCast](../../../docs/reference/serialization/casts.md) | CastRegistry, JsonCast | HydrationCastSourcesTest, JsonErrorContractsTest |
| [Один набор: Returns, cached response, pagination, composite, await](../../../docs/reference/dto/field-rules.md) | AbstractClient, Hydrator | ExternalHydrationEntriesTest |

## Порция C

| Тема и единственный владелец | Код | Сценарии |
| --- | --- | --- |
| [Authenticator, auth scope и политика доступа](../../../docs/reference/auth/strategies.md) | AuthenticatorInterface | AuthPolicyScopeTest, AuthScopeResetTest, AuthorizationSchemeAuthenticatorTest |
| [Token cache, refresh lock, восстановление после 401](../../../docs/reference/auth/tokens.md) | TokenAuthenticator | AuthRecoveryContractTest, AuthRefreshLockTest, TokenIsolationTest |
| [Credentials enrichment, merge и изоляция назначения](../../../docs/reference/auth/credentials.md) | CredentialsEnrichmentConfig | CredentialsEnrichmentTest, ExternalUrlContractTest |
| [Retry, Retry-After, идемпотентность и replay](../../../docs/reference/execution/retry.md) | RetryConfig, RetryHandler | SafeRetryContractTest, RetryAfterContractTest, StreamReplayContractTest |
| [Совместные квоты и backend](../../../docs/reference/execution/rate-limit.md) | RateLimitConfig, RateLimiter | JointQuotaTest, RateLimitExecutionTest |
| [Кеш raw HTTP-ответов, identity и overrides](../../../docs/reference/execution/cache.md) | CacheConfig, CacheManager | AutomaticCacheIdentityTest, CacheIsolationTest, CacheManagerOverridesTest |
| [Timeout, delay и общий deadline](../../../docs/reference/execution/deadlines.md) | TransportOptions | TransportTimeoutIntegrationTest, RetrySenderTotalTimeoutTest |
| [Пагинация, metadata, items-only и guards](../../../docs/reference/execution/pagination.md) | PaginationConfig, PaginationRule | PaginationFailureContractTest, PaginatorGuardTest, ExternalHydrationEntriesTest |
| [Batch/pool, ошибки и наследование контекста](../../../docs/reference/execution/batch-pool.md) | BatchExecutor, PoolExecutor | BatchFailureContractTest, CoreFlowIntegrationTest |
| [Pending/Ready/Failed, context и режимы](../../../docs/reference/execution/continuation-state.md) | ContinuationContext, ContinuationState, FinalPathStateResolver | ContinuationReadinessTest |
| [Await, token-only, лимиты, Outcome и cached awaitAs](../../../docs/reference/execution/continuation-await.md) | ContinuationService, ContinuationOutcome | ResultHandleContinuationAwaitTest, ContinuationReadinessTest |
| [Upload, stream replay и ограничения файлов](../../../docs/reference/files/uploads.md) | FileInput | SerializerFilesTest, StreamingFileTransportTest |
| [Download, destination, Base64 и владение файлами](../../../docs/reference/files/downloads.md) | FileResponse, Base64File | StreamingFileTransportTest |
| [Архивы, драйверы и временные файлы](../../../docs/reference/files/archives.md) | ArchiveConfig, ArchiveResponse | ArchiveResponseTest, ArchiveTempFileManagerTest |

## Порция D

| Тема и единственный владелец | Код | Сценарии |
| --- | --- | --- |
| [Composite, зависимости, RequestRole и результаты](../../../docs/reference/request/composition.md) | CompositeRequestInterface, DependsOnRequestInterface | CoreFlowIntegrationTest, PipelineIntegrationTest |
| [Публичные hooks и boundaries](../../../docs/reference/extensions/hooks.md) | HookRegistry, HookInterface | HookRunnerTest, PipelineHookExceptionsTest, BeforeHydrateHookDataTest |
| [Extensions, MIME handler, lifecycle и конфликты](../../../docs/reference/extensions/extensions.md) | ExtensionRegistry, ResponseHandlerInterface | ExtensionRegistryPriorityTest, ExtensionResponseHandlerTest |
| [Атрибуты: сигнатуры, targets и defaults](../../../docs/reference/attributes/README.md) | AttributeRegistry | AttributeRegistryTest, AboutAttributeTest, SerializerRequestDefaultsTest |
| [Валидация, фабрики и standalone без приложения](../../../docs/reference/client/validation.md) | Validator | ScopedValidationTest, ValidatorTest, PipelineValidationOrderTest |
| [Laravel provider, bindings, payload factory и workers](../../../docs/reference/integrations/laravel.md) | SdkServiceProvider | verify |
| [Redis, общая квота, время и доступность](../../../docs/reference/integrations/redis.md) | PhpRedisRateLimitBackend | RedisRateLimitTest |
| [Fake, sequence, assertions и oneOf helper](../../../docs/reference/testing/mocking.md) | MockTransport, RequestContractTestHelper | MockTransportTest, RequestContractTestHelperTest |
| [Recording/playback и redaction фикстур](../../../docs/reference/testing/fixtures.md) | RecordingTransport, Fixture | TransportTestingTest |
| [Live helpers и граница provider tooling](../../../docs/reference/testing/live.md) | LiveEnvLoader, LivePolling, LiveResultAssertions | LiveEnvLoaderTest, LivePollingTest, LiveResultAssertionsTest |
| [Статические provider catalogs и request-bound каталог](../../../docs/reference/client/catalogs.md) | ProviderCatalogRegistryInterface | ProviderCatalogRegistryTest |
| [Read-only Operation Inventory и sdkCallPaths](../../../docs/reference/client/operation-inventory.md) | OperationInventoryBuilder, SdkCallPathResolver | OperationInventoryBuilderTest |
| [Производный ResponseDtoCatalog, multi-service и export](../../../docs/reference/client/response-dto-catalog.md) | ResponseDtoCatalog, MultiServiceResponseDtoCatalogFactory | MultiServiceResponseDtoCatalogFactoryTest |
| [Версии: explicit и compat-default](../../../docs/reference/client/versioning.md) | VersionedResourceTrait | VersionedResourceTraitTest |
| [Trace, audit, redaction и ограничение объёма логов](../../../docs/reference/results/observability.md) | ExecutionResult | ExecutionResultRequestDebugTest, TraceErrorContextContractTest |

## Закрытые пробелы

Quickstart передаёт конфигурацию и транспорт; Laravel binding регистрирует namespace запросов.
Убраны обещания non-blocking/fire-and-forget и «будущего» Result API. Валидация без приложения
опирается на явно переданную фабрику. В глоссарии отсутствующие AttributeScanner и
RefreshTokenException обозначены как прежние названия, а внутренние помощники ведут в development.
Входные даты с invalidBehavior=Null для non-nullable поля описаны как HydrationException.
Добавлены точные сигнатуры всех 50 атрибутов, в том числе ранее не описанные классовые профили.
Сохранены границы 028–031: scope, strict, extras/wire, sourcePath, одиночный Nested, provider path,
изоляция объектов, явная готовность await и доставка ошибок.

## Проверки

Полный Pest: 2027 passed, 7308 assertions; 17 Redis-проверок пропущены без отдельного стенда.
Первый запуск внутри sandbox не смог поднять локальные HTTP-серверы; повтор вне sandbox прошёл.
Логи сохранены отдельно и не переписаны. Laravel binding выполнен в отдельном Laravel 12 приложении.
Публичные примеры выполняются также в проверке документации и затем в обоих архивах.
Сценарии не добавлялись только ради переноса прозы; новый checker имеет проверки реальных отказов.
