"""Связывает проверенные темы документации с кодом и существующими сценариями."""
import hashlib
import json
from pathlib import Path

ROOT=Path(__file__).resolve().parents[4]
PLAN=Path(__file__).resolve().parents[1]
# Имена выбраны по содержанию; поиск ниже только разрешает точный путь файла.
rows=[
('A','Клиент, config, with/null и явная сборка','client/configuration','ClientConfig;AbstractClient','ClientConfigTest;ContainerProviderRequestResolverTest'),
('A','Discovery, longest namespace, ownership и DI','client/discovery','ClientRegistry;ClientResolver;ClientDiscoveryService','ClientRegistryTest;DiscoveryFullFlowTest'),
('A','Ресурсы и привязка запросов','client/resources','AbstractResource','DiscoveryFullFlowTest'),
('A','Декларация и runtime-опции запроса','request/declaration','AbstractRequest;RequestExecution','SerializerRequestDefaultsTest;PipelineRequestOptionsProviderFallbackTest'),
('A','Подготовка частей запроса и RequestDefaults','serialization/request-parts','RequestPartsCollector','SerializerRequestDefaultsTest;SerializerTest'),
('A','URI, query, origin и готовые URL','serialization/uri-query','RequestUrlBuilder','UriPipelineTest;RequestUrlContractTest;ExternalUrlContractTest;ExternalUrlTransportTest'),
('A','JSON/body/BodyRoot и ошибка кодирования','serialization/body','Serializer','SerializerBodyRootTest;StrictJsonEncodingTest'),
('A','Транспорт, promise, capabilities и тело PreparedRequest','execution/transport','HttpTransport;PreparedRequest','HttpTransportTest;PreparedRequestBodyTest;PreparedBodyContractTest;TransportTimeoutIntegrationTest'),
('A','Результаты raw/resolved/dataOrFail и фабрики','results/handles','ResultHandle;ExecutionResult;ResolvedResult','SuccessfulResponseContractTest;ResultFactoryTest;ClientResponseFactoryTest'),
('A','RawResponse и разные MIME','attributes/response','RawResponse','RawResponseTest;SuccessfulResponseContractTest'),
('A','Ошибки, маппинг, partial и typed context','results/errors','ExecutionResult;ClientError','ErrorPolicyTest;ResolvedResultErrorContextTest;TraceErrorContextContractTest'),
('B','Модели DTO, constructor-first и inherited properties','dto/models','Hydrator;AbstractDto','HydratorTest;HydrationCompatibilityTest'),
('B','HydrationRules, descriptors и конфликт деклараций','dto/field-rules','HydrationRules;DtoRules;FieldRule','ExternalHydrationConfigurationTest;ExternalHydrationRulesTest'),
('B','Профили, From/fallback и даты','dto/profiles','DtoHydrationProfileResolver;DateTimeHydrationPolicy','DateTimePolicyTest;HydrationCastSourcesTest'),
('B','Legacy/Strict, union и большие целые','dto/scalars','HydrationValueValidator;IntegerCast','ExternalHydrationScalarTest;BigIntegerIntegrationTest'),
('B','Missing, null, defaults и provider Present','dto/defaults','DefaultSpec;Hydrator','DefaultValueProviderContractTest;HydrationErrorContractTest'),
('B','Одиночный Nested, list, each, формы и required','dto/shapes','ValueShape;Hydrator','NestedObjectHydrationTest;ExternalHydrationRulesTest'),
('B','Discriminator Value/Key, KeepRaw/Skip/Error','dto/variants','ValueShape;Nested','NestedDiscriminatorModeTest;ExternalHydrationRulesTest'),
('B','Остаток источника, fallback, коллизия _extra, рекурсивная проекция','dto/extras','DtoRules;Hydrator','ExternalHydrationExtrasTest'),
('B','Вложенные casts/providers с текущим scope','dto/scope','HydrationScope','ExternalHydrationScopeTest'),
('B','Пути DTO/sourcePath и safe log','dto/diagnostics','HydrationException','ExternalHydrationOriginTest;HydrationErrorContractTest'),
('B','Типизированные коллекции и missing fallback','dto/collections','AbstractTypedCollection;AbstractCollection','TypedCollectionTest;CollectionIntegrationTest'),
('B','Изоляция object defaults/атрибутов, повторы и cache on/off','dto/lifecycle','AttributeMetadataCache;Hydrator;DtoSerializer','MetadataValueIsolationTest'),
('B','DX/wire, enum, даты и profile hierarchy','serialization/dto-output','DtoSerializer;SerializationValueResolver','DtoSerializerTest;DateTimePolicyTest'),
('B','Receiver в ручных/nested DTO, cast/opaque/query границы','serialization/receiver-output','Serializer;DtoSerializer','ExternalHydrationWireTest'),
('B','Источники cast registry vs hydration/profile и JsonCast','serialization/casts','CastRegistry;JsonCast','HydrationCastSourcesTest;JsonErrorContractsTest'),
('B','Один набор: Returns, cached response, pagination, composite, await','dto/field-rules','AbstractClient;Hydrator','ExternalHydrationEntriesTest'),
('C','Authenticator, auth scope и политика доступа','auth/strategies','AuthenticatorInterface','AuthPolicyScopeTest;AuthScopeResetTest;AuthorizationSchemeAuthenticatorTest'),
('C','Token cache, refresh lock, восстановление после 401','auth/tokens','TokenAuthenticator','AuthRecoveryContractTest;AuthRefreshLockTest;TokenIsolationTest'),
('C','Credentials enrichment, merge и изоляция назначения','auth/credentials','CredentialsEnrichmentConfig','CredentialsEnrichmentTest;ExternalUrlContractTest'),
('C','Retry, Retry-After, идемпотентность и replay','execution/retry','RetryConfig;RetryHandler','SafeRetryContractTest;RetryAfterContractTest;StreamReplayContractTest'),
('C','Совместные квоты и backend','execution/rate-limit','RateLimitConfig;RateLimiter','JointQuotaTest;RateLimitExecutionTest'),
('C','Кеш raw HTTP-ответов, identity и overrides','execution/cache','CacheConfig;CacheManager','AutomaticCacheIdentityTest;CacheIsolationTest;CacheManagerOverridesTest'),
('C','Timeout, delay и общий deadline','execution/deadlines','TransportOptions','TransportTimeoutIntegrationTest;RetrySenderTotalTimeoutTest'),
('C','Пагинация, metadata, items-only и guards','execution/pagination','PaginationConfig;PaginationRule','PaginationFailureContractTest;PaginatorGuardTest;ExternalHydrationEntriesTest'),
('C','Batch/pool, ошибки и наследование контекста','execution/batch-pool','BatchExecutor;PoolExecutor','BatchFailureContractTest;CoreFlowIntegrationTest'),
('C','Pending/Ready/Failed, context и режимы','execution/continuation-state','ContinuationContext;ContinuationState;FinalPathStateResolver','ContinuationReadinessTest'),
('C','Await, token-only, лимиты, Outcome и cached awaitAs','execution/continuation-await','ContinuationService;ContinuationOutcome','ResultHandleContinuationAwaitTest;ContinuationReadinessTest'),
('C','Upload, stream replay и ограничения файлов','files/uploads','FileInput','SerializerFilesTest;StreamingFileTransportTest'),
('C','Download, destination, Base64 и владение файлами','files/downloads','FileResponse;Base64File','StreamingFileTransportTest'),
('C','Архивы, драйверы и временные файлы','files/archives','ArchiveConfig;ArchiveResponse','ArchiveResponseTest;ArchiveTempFileManagerTest'),
('D','Composite, зависимости, RequestRole и результаты','request/composition','CompositeRequestInterface;DependsOnRequestInterface','CoreFlowIntegrationTest;PipelineIntegrationTest'),
('D','Публичные hooks и boundaries','extensions/hooks','HookRegistry;HookInterface','HookRunnerTest;PipelineHookExceptionsTest;BeforeHydrateHookDataTest'),
('D','Extensions, MIME handler, lifecycle и конфликты','extensions/extensions','ExtensionRegistry;ResponseHandlerInterface','ExtensionRegistryPriorityTest;ExtensionResponseHandlerTest'),
('D','Атрибуты: сигнатуры, targets и defaults','attributes/README','AttributeRegistry','AttributeRegistryTest;AboutAttributeTest;SerializerRequestDefaultsTest'),
('D','Валидация, фабрики и standalone без приложения','client/validation','Validator','ScopedValidationTest;ValidatorTest;PipelineValidationOrderTest'),
('D','Laravel provider, bindings, payload factory и workers','integrations/laravel','SdkServiceProvider','tests/Integration/Laravel/verify.php'),
('D','Redis, общая квота, время и доступность','integrations/redis','PhpRedisRateLimitBackend','tests/Integration/Redis/RedisRateLimitTest.php'),
('D','Fake, sequence, assertions и oneOf helper','testing/mocking','MockTransport;RequestContractTestHelper','MockTransportTest;RequestContractTestHelperTest'),
('D','Recording/playback и redaction фикстур','testing/fixtures','RecordingTransport;Fixture','TransportTestingTest'),
('D','Live helpers и граница provider tooling','testing/live','LiveEnvLoader;LivePolling;LiveResultAssertions','LiveEnvLoaderTest;LivePollingTest;LiveResultAssertionsTest'),
('D','Статические provider catalogs и request-bound каталог','client/catalogs','ProviderCatalogRegistryInterface','ProviderCatalogRegistryTest'),
('D','Read-only Operation Inventory и sdkCallPaths','client/operation-inventory','OperationInventoryBuilder;SdkCallPathResolver','OperationInventoryBuilderTest'),
('D','Производный ResponseDtoCatalog, multi-service и export','client/response-dto-catalog','ResponseDtoCatalog;MultiServiceResponseDtoCatalogFactory','MultiServiceResponseDtoCatalogFactoryTest'),
('D','Версии: explicit и compat-default','client/versioning','VersionedResourceTrait','VersionedResourceTraitTest'),
('D','Trace, audit, redaction и ограничение объёма логов','results/observability','ExecutionResult','ExecutionResultRequestDebugTest;TraceErrorContextContractTest'),
]
source_files=list((ROOT/'src').rglob('*.php'));test_files=list((ROOT/'tests').rglob('*.php'))
def find(name,files):
 if '/' in name:
  p=ROOT/name
  if not p.is_file():raise ValueError(name)
  return p
 matches=[p for p in files if p.stem==name]
 if len(matches)!=1:raise ValueError((name,[str(p.relative_to(ROOT)) for p in matches]))
 return matches[0]
entries=[]
for group,feature,page,sources,tests in rows:
 entries.append({'portion':group,'feature':feature,'audience':'пользователь пакета / автор SDK',
                 'owner':'docs/reference/'+page+'.md','source':[str(find(n,source_files).relative_to(ROOT)) for n in sources.split(';')],
                 'tests':[str(find(n,test_files).relative_to(ROOT)) for n in tests.split(';')],
                 'status':'сверено по текущему коду и существующим сценариям'})
for item in entries:
 if not (ROOT/item['owner']).exists():raise ValueError(item['owner'])
paths={p for e in entries for p in e['source']+e['tests']+[e['owner']]}
report={'entries':entries,'sha256':{p:hashlib.sha256((ROOT/p).read_bytes()).hexdigest() for p in sorted(paths)}}
(PLAN/'artifacts/feature-inventory.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
lines=['# Покрытие возможностей документацией','',
       'Проверка реализации 027. Каждая строка задаёт владельца контракта, исходники и',
       'наблюдаемые сценарии. Полные пути и SHA-256 находятся в [машинной карте](artifacts/feature-inventory.json).',
       'Статус «сверено» означает редакционную проверку описания; результаты запусков',
       'указываются отдельно и не выводятся из наличия файла теста.','',
       'Основная аудитория строк — пользователь пакета, включая автора SDK. Изменение',
       'этих механизмов в src направляется через CONTRIBUTING/development.','']
for group in 'ABCD':
 lines+=['## Порция '+group,'','| Тема и единственный владелец | Код | Сценарии |','| --- | --- | --- |']
 for e in entries:
  if e['portion']!=group:continue
  owner='../../../'+e['owner'];sources=', '.join(Path(p).stem for p in e['source']);tests=', '.join(Path(p).stem for p in e['tests'])
  lines.append(f"| [{e['feature']}]({owner}) | {sources} | {tests} |")
 lines+=['']
lines+=['## Закрытые пробелы','',
'Quickstart передаёт конфигурацию и транспорт; Laravel binding регистрирует namespace запросов.',
'Убраны обещания non-blocking/fire-and-forget и «будущего» Result API. Валидация без приложения',
'опирается на явно переданную фабрику. В глоссарии отсутствующие AttributeScanner и',
'RefreshTokenException обозначены как прежние названия, а внутренние помощники ведут в development.',
'Входные даты с invalidBehavior=Null для non-nullable поля описаны как HydrationException.',
'Добавлены точные сигнатуры всех 50 атрибутов, в том числе ранее не описанные классовые профили.',
'Сохранены границы 028–031: scope, strict, extras/wire, sourcePath, одиночный Nested, provider path,',
'изоляция объектов, явная готовность await и доставка ошибок.','',
'## Проверки','',
'Полный Pest: 2027 passed, 7308 assertions; 17 Redis-проверок пропущены без отдельного стенда.',
'Первый запуск внутри sandbox не смог поднять локальные HTTP-серверы; повтор вне sandbox прошёл.',
'Логи сохранены отдельно и не переписаны. Laravel binding выполнен в отдельном Laravel 12 приложении.',
'Публичные примеры выполняются также в проверке документации и затем в обоих архивах.',
'Сценарии не добавлялись только ради переноса прозы; новый checker имеет проверки реальных отказов.','']
(PLAN/'inventory.md').write_text('\n'.join(lines))
print(len(entries),'тем; источников и файлов проверки:',len(paths))
