# Параметры ClientConfig

[Обзор на одном примере](../../guides/client/showcase.md) показывает, как настройки
меняют выполнение запросов. Ниже находится полный каталог параметров.

`Brahmic\ApiSutra\Config\ClientConfig` — неизменяемая конфигурация одного клиента.
Обязателен `baseUrl`; транспорт передаётся отдельно в конструктор клиента.
[Полная сборка](construction.md) и [исполняемый пример](../../example/sdk/src/Config/ClientConfigFactory.php).

## Создать и изменить

Фрагмент для приложения с Composer autoload:

```php
use Brahmic\ApiSutra\Config\ClientConfig;

$config = new ClientConfig(baseUrl: 'https://api.example.test');
$another = $config->with(timeout: 15, hydrationRules: null);
```

`with()` создаёт новую конфигурацию, сохраняет поля без override и учитывает явный
`null`. Например, `with(hydrationRules: null)` отключает набор в копии. Изменение
конфигурации не перестраивает уже созданного клиента: используйте копию для нового
экземпляра. `fromLaravel(array $overrides)` подбирает debug/environment из Laravel;
внешние правила собирайте в фабрике/provider, а не в кешируемом config-массиве.

Конструктор проверяет сочетания параметров и выбрасывает `ConfigurationException`
при неверной конфигурации. Значения `timeout`/`connectTimeout` задаются в секундах,
`delay` — в миллисекундах; остальные единицы указаны у тематических владельцев.

## Каталог параметров

Таблица перечисляет все параметры конструктора. Полные defaults, приоритеты и
ограничения хранятся в соответствующем разделе, а не дублируются здесь.

| Параметр | PHP-тип | Владелец контракта |
| --- | --- | --- |
| `baseUrl` | `string` | [Сборка URI и переопределение адреса](../serialization/uri-query.md) |
| `auth` | `?AuthenticatorInterface` | [Выбор auth](../auth/strategies.md) |
| `authScopes` | `array` | [Выбор auth](../auth/strategies.md) |
| `authPolicy` | `?AuthPolicyInterface` | [Выбор auth](../auth/strategies.md) |
| `authRetryOn401` | `bool` | [Refresh и 401](../auth/tokens.md) |
| `authRetryAttempts` | `int` | [Refresh и 401](../auth/tokens.md) |
| `logger` | `?LoggerInterface` | [Логи и debug](../results/observability.md) |
| `logLevel` | `string` | [Логи и debug](../results/observability.md) |
| `cacheStore` | `?CacheInterface` | Единственный PSR-16 store для HTTP и общего auth-кеша |
| `cacheConfig` | `?CacheConfig` | [Параметры кеша](../execution/cache.md), без store |
| `timeout` | `int` | [Лимиты времени](../execution/deadlines.md) |
| `connectTimeout` | `int` | [Лимиты времени](../execution/deadlines.md) |
| `retry` | `?RetryConfig` | [Повторные попытки](../execution/retry.md) |
| `rateLimit` | `?RateLimitConfig` | [Квоты](../execution/rate-limit.md) |
| `pool` | `?PoolConfig` | [Batch и pool](../execution/batch-pool.md) |
| `queryArrayFormat` | `QueryArrayFormat` | [Формат query](../serialization/uri-query.md) |
| `serializeNulls` | `bool` | [Сборка частей запроса](../serialization/request-parts.md) |
| `namingStrategy` | `NamingStrategy` | [Сборка частей запроса](../serialization/request-parts.md) |
| `casts` | `array` | [Исходящие casts](../serialization/casts.md) |
| `dtoSerializationProfile` | `?DtoSerializationProfileInterface` | [DTO: DX и wire](../serialization/dto-output.md) |
| `wireBodySerializationPolicy` | `?DtoSerializationPolicy` | [DTO: DX и wire](../serialization/dto-output.md) |
| `requestPartsEnumOutput` | `EnumOutput` | [Значения частей запроса](../serialization/request-parts.md) |
| `requestPartsStrictEnums` | `bool` | [Значения частей запроса](../serialization/request-parts.md) |
| `requestDateTime` | `?DateTimeSerializationPolicy` | [Даты в исходящем представлении](../serialization/dto-output.md) |
| `delay` | `int` | [Лимиты времени](../execution/deadlines.md) |
| `throwOnErrors` | `bool` | [Доставка ошибок](../results/errors.md) |
| `debug` | `bool` | [Логи и debug](../results/observability.md) |
| `environment` | `Environment` | [Логи и debug](../results/observability.md) |
| `idempotencyHeader` | `string` | [Повторные попытки](../execution/retry.md) |
| `extensions` | `array` | [Расширения](../extensions/extensions.md) |
| `paginationConfig` | `?PaginationConfig` | [Пагинация](../execution/pagination.md) |
| `archive` | `?ArchiveConfig` | [Архивы](../files/archives.md) |
| `paginationRule` | `?PaginationRule` | [Пагинация](../execution/pagination.md) |
| `resolvedResultFactory` | `?ResolvedResultFactoryInterface` | [Представление результата](../results/handles.md) |
| `errorMapper` | `?ClientErrorMapperInterface` | [Маппинг ошибок](../results/errors.md) |
| `errorContextFactory` | `?ErrorContextFactoryInterface` | [Маппинг ошибок](../results/errors.md) |
| `responseFactory` | `?ClientResponseFactoryInterface` | [Представление результата](../results/handles.md) |
| `containerProvider` | `?ContainerProviderInterface` | [Контейнер и явная сборка](construction.md) |
| `requestEnrichers` | `array` | [Credentials и origin](../auth/credentials.md) |
| `credentialsConfig` | `?CredentialsEnrichmentConfig` | [Credentials и origin](../auth/credentials.md) |
| `continuationTokenExtractor` | `?ContinuationTokenExtractorInterface` | [Ожидание и token](../execution/continuation-await.md) |
| `resultMetaExtractor` | `?ResultMetaExtractorInterface` | [Runtime meta результата](../results/handles.md#provider-resultmetaextractor) |
| `providerCatalogRegistry` | `?ProviderCatalogRegistryInterface` | [Каталоги SDK](catalogs.md) |
| `defaultContinuationMode` | `ContinuationMode` | [Состояние операции](../execution/continuation-state.md) |
| `defaultPollRequest` | `?string` | [Ожидание и token](../execution/continuation-await.md) |
| `continuationModeApplicator` | `?ContinuationModeApplicatorInterface` | [Состояние операции](../execution/continuation-state.md) |
| `redaction` | `RedactionPolicy` | [Логи и debug](../results/observability.md) |
| `textBooleanFormat` | `BooleanFormat` | [Значения частей запроса](../serialization/request-parts.md) |
| `originPolicy` | `OriginPolicy` | [Credentials и origin](../auth/credentials.md) |
| `includeClientQuota` | `bool` | [Квоты](../execution/rate-limit.md) |
| `rateLimitBackend` | `?RateLimitBackendInterface` | [Квоты](../execution/rate-limit.md) |
| `continuationStateResolver` | `?ContinuationStateResolverInterface` | [Состояние операции](../execution/continuation-state.md) |
| `hydrationRules` | `?HydrationRules` | [Правила DTO](../dto/field-rules.md) и [исходящий receiver](../serialization/receiver-output.md) |

## Граница конфигурации клиента

Runtime-override отдельного запроса не меняет ClientConfig. Параметры выбираются
по тематическому контракту: единое правило «любой override всегда сильнее всего»
не заменяет особенности auth, накопления квот, wire-политики или внешних правил.

HTTP-кеш хранит ответ провайдера, не готовый DTO. Кеш метаданных — отдельный механизм;
его включение не должно менять изоляцию object defaults и аргументов атрибутов.
[Жизненный цикл DTO](../dto/lifecycle.md).

[Раздел клиента](README.md).

`cacheStore` и `cacheConfig` независимы: `with()` сохраняет каждый незаданный аргумент,
явный null сбрасывает только названное поле. [Отключение и замена настроек](../execution/cache.md#копирование-и-отключение).
