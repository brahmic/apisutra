# Чеклист разработки провайдера

Дорожная карта и маршрутный лист для создания SDK провайдера. Каждый пункт содержит пояснение и ссылку на детальное описание. Разработчик копирует (git mv, cp или как-то иначе) чеклист к себе и заполняет колонку «Мои заметки» по ходу работы.

**Связанная документация:** [Методология провайдера](./provider-methodology.md).

---

## Блок 1: Архитектура и модель

| № | Пункт | Пояснение / подсказка | Ссылка на детали | Мои заметки |
|---|-------|------------------------|------------------|-------------|
| 1.1 | Карта решений → единая модель | Начните с [анализа провайдера](./provider-analysis.md). Зафиксируйте: enums (операции, статусы, ошибки), модель ошибок, DTO, пагинацию, группировку по ресурсам. Сразу добавляйте `title()` во все enum. | [provider-methodology.md#1-карта-решений](provider-methodology.md#1-карта-решений--единая-модель) | |
| 1.2 | Базовые классы определены | BaseClient, BaseRequest, BaseResource, BaseDto, BaseResponseDto. Централизуют дефолты и правила. BaseDto/BaseResponseDto — рекомендуемая точка binding для `DtoHydrationProfile` и `DtoSerializationProfile`. | [provider-methodology.md#2-базовые-классы](provider-methodology.md#2-базовые-классы-вместо-копипаста) | |
| 1.3 | Запросы и DTO наследуют от base | Конкретные запросы → BaseRequest, конкретные DTO → BaseDto/BaseResponseDto. Исключает правки десятков классов при изменении правил. | [provider-methodology.md#2](provider-methodology.md#2-базовые-классы-вместо-копипаста) | |
| 1.4 | Структура папок и владение | Ресурс как владелец: запросы/DTO/enum внутри Resources/&lt;Resource&gt;. Domain/ — только provider-wide. Не допускайте плоской свалки в `Dto/Response` или `Dto/Request`: при росте числа DTO разбивайте по подпапкам (endpoint, поддомен, тип ответа, сценарий). | [provider-methodology.md#21-структура](provider-methodology.md#21-структура-и-владение-сущностями) | |
| 1.5 | Повторяющиеся тела в DTO | Одинаковое тело в нескольких запросах → вынести в Body DTO, использовать как свойство. | [provider-methodology.md#2](provider-methodology.md#2-базовые-классы-вместо-копипаста), [requests.md](requests.md) | |
| 1.6 | ClientConfig через фабрику | Конфиг собирается отдельным классом/фабрикой — конфиг остаётся тонким, не «в лоб» в конструкторе Client. | [provider-methodology.md#21-структура](provider-methodology.md#21-структура-и-владение-сущностями) | |
| 1.7 | DTO/Enums не образуют плоский хаос | Если в одной папке начинает скапливаться заметное число DTO/enum, сгруппируйте их по подпапкам заранее. Плоская папка допустима только для маленького ресурса с несколькими файлами. | [provider-methodology.md#каноническая-структура-endpoint](provider-methodology.md#каноническая-структура-endpoint-настоятельная-рекомендация) | |

---

## Блок 2: DTO — свойства, атрибуты, хуки

| № | Пункт | Пояснение / подсказка | Ссылка на детали | Мои заметки |
|---|-------|------------------------|------------------|-------------|
| 2.1 | Типы и свойства DTO | readonly, типизированные свойства. Глубина определяется контрактом API и нужными потребителю данными; для сложной вложенности проверьте читаемость и группировку типов. Типы дат: DateTimeImmutable/Carbon. Для обычных scalar-полей (`int`, `float`, `bool`, `string`) ядро уже делает safe auto-cast при гидрации. Если DTO строится по inheritance-модели, constructor-first остаётся основным контрактом, но конечный DTO теперь может добавлять public hydrated properties вне constructor chain. Если провайдер системно присылает `''` вместо отсутствия значения, продумайте `EmptyStringAsNull` или hydration profile-level `emptyStringBehavior`. | [dto.md](dto.md), [glossary/dto.md](../glossary/dto.md), [casts.md](casts.md) | |
| 2.2 | Маппинг: #[Map], #[From], #[To], #[Cast] | `Map` — когда один и тот же внешний ключ нужен в обе стороны. `From` — для входящих, `To` — для исходящих. Для дат типовой DX теперь через `DateTimeFrom` / `DateTimeTo`; `Cast` оставляйте для нестандартного формата или кастомной логики. Приоритеты: hydrate `From -> Map -> NamingStrategy`; для scalar built-in типов дальше работает safe auto-cast; serialize `To -> Map -> NamingStrategy`. | [attributes/data-transfer.md](attributes/data-transfer.md), [dto.md](dto.md), [casts.md](casts.md) | |
| 2.3 | #[Nested] и коллекции | Вложенные объекты и массивы — `#[Nested(type: XDto::class)]`. Типизированные коллекции — `AbstractTypedCollection` + `itemsCollectionFactory`. Для non-nullable typed collection `missing -> empty collection` уже покрывается ядром; явный `DefaultValue([])` нужен в основном для `null` или явной фиксации контракта. `DefaultValue(... when: [Null])` при этом не ломает built-in fallback для `Missing`. Полиморфные массивы — discriminator + map. Mixed-структуры — `RawCollection`. | [dto.md](dto.md), [collections.md](collections.md) | |
| 2.4 | RequestOneOf / RequestDiscriminator | Взаимоисключающие payload-варианты — `RequestOneOf` + `RequestDiscriminator`. Не ручные assert. | [attributes/request.md#requestoneof](attributes/request.md#requestoneof) | |
| 2.5 | Валидация DTO | `#[Validate]`, `#[Label]` для читаемых ошибок. `CustomValidatableRequestInterface` — preflight до сериализации (файлы, кросс-полевая логика). | [validation.md](validation.md) | |
| 2.6 | Naming strategy | Для DTO hydration naming fallback рекомендуется централизовать через `DtoHydrationProfile`, для body DTO naming policy — через `DtoSerializationProfile`. Для request/query/header/path fallback по-прежнему используется клиентский config. | [naming-strategy.md](naming-strategy.md), [client-config/serialization.md](client-config/serialization.md) | |
| 2.7 | #[DefaultValue] | Когда поле может отсутствовать — `#[DefaultValue(value: X)` или `when: ValueState::Missing`. Провайдер для контекстно-зависимых значений. Для non-nullable typed collection `Missing -> []` можно не дублировать: это уже built-in fallback. | [attributes/data-transfer.md](attributes/data-transfer.md) | |

---

## Блок 3: Протокол и конфигурация

| № | Пункт | Пояснение / подсказка | Ссылка на детали | Мои заметки |
|---|-------|------------------------|------------------|-------------|
| 3.1 | Протокольные нюансы зафиксированы | До разработки: path-параметры, query format, auth placement, полиморфные массивы в ответах. Под это выбираются `#[Path]`, `QueryArrayFormat`, `DependsOnRequestInterface`. | [provider-methodology.md#31](provider-methodology.md#31-нюансы-протокола-фиксируйте-заранее) | |
| 3.2 | Provider credentials | Креды в body/query/form — централизованно в `ClientConfig::credentialsConfig`. Defaults, scopes, merge mode, secretKeys для redaction. Request описывает только endpoint-специфику. | [provider-methodology.md#30](provider-methodology.md#30-provider-credentials-рекомендуемый-подход), [client-config/auth.md](client-config/auth.md) | |
| 3.3 | Глобальные политики в ClientConfig | retry, rate-limit, cache, timeouts, throwOnErrors — в ClientConfig. Атрибуты — специфика endpoint. | [provider-methodology.md#3](provider-methodology.md#3-где-задавать-правила), [client-config/README.md](client-config/README.md) | |
| 3.3a | DTO hydration/serialization profiles | **Настоятельная рекомендация:** задайте `DtoHydrationProfile` для hydration DTO и `DtoSerializationProfile` для DX DTO. Для wire body по умолчанию используйте safe transport policy; если нужно, явно задайте `wireBodySerializationPolicy`. Для query/header/path задавайте отдельные request-level rules в `ClientConfig`. | [client-config/serialization.md](client-config/serialization.md#dtoserializationprofile), [serialization.md](serialization.md#enum-сериализация) | |
| 3.4 | Endpoint-особенности в атрибутах | Path, Query, Pagination, Download. Много body-полей → `RequestDefaults`. Root-body `[...]` (JSON Patch, bulk) → `BodyRoot`. OneOf — `RequestOneOf` + `RequestDiscriminator`. | [attributes/request.md](attributes/request.md), [attributes/README.md](attributes/README.md) | |
| 3.5 | DependsOnRequest | Подготовительные/системные запросы, зависимость «ключ → данные» — `DependsOnRequestInterface`. | [provider-methodology.md#31](provider-methodology.md#31-нюансы-протокола-фиксируйте-заранее), [request-pipeline.md](request-pipeline.md) | |
| 3.6 | Хуки (hooks) | BeforeSend (trace-id, заголовки, подписи), AfterResponse (логирование, метрики), BeforeHydrate/AfterHydrate (нормализация DTO). | [hooks.md](hooks.md), [attributes/hooks.md](attributes/hooks.md) | |
| 3.7 | Rate Limit и Retry | `RateLimitConfig`, `RetryConfig`, `#[RateLimit]`, `#[Retry]`. Поведение при 429, Retry-After, retryOn. Rate-limit применяется независимо от идемпотентности; retry включается при подтверждённой безопасности повтора по контракту API. | [retries-rate-limit.md](retries-rate-limit.md), [client-config/rate-limit.md](client-config/rate-limit.md), [client-config/retry.md](client-config/retry.md) | |
| 3.8 | Файлы и архивы | Загрузка: `#[File]` (Multipart/Binary/Base64). Скачивание: `#[Download]`. Архивы — `ArchiveExtension`, `ArchiveConfig`. `tryFromPath()` для path-flow без исключений. | [files.md](files.md), [client-config/archive.md](client-config/archive.md) | |

---

## Блок 4: Аутентификация и окружение

| № | Пункт | Пояснение / подсказка | Ссылка на детали | Мои заметки |
|---|-------|------------------------|------------------|-------------|
| 4.1 | Аутентификация | Тип и формат (header/query), scopes, 401/403, ротация/refresh. Оформляйте в AuthenticatorInterface, AuthPolicyInterface. | [provider-methodology.md#32](provider-methodology.md#32-аутентификация-детализация), [auth.md](auth.md) | |
| 4.2 | Sandbox / тестирование API | baseUrl, тестовые креды, rate-limit для sandbox, стратегия моков/фикстур. Разделить ClientConfig prod/sandbox. | [provider-methodology.md#33](provider-methodology.md#33-sandbox-и-тестирование-api), [testing.md](testing.md) | |
| 4.3 | Мультисервисность | Несколько API с разными baseUrl/auth/pagination → рассмотреть мегаклиент. Один клиент + ресурсы — если различий нет. | [provider-methodology.md#34](provider-methodology.md#34-мультисервисность-и-мегаклиент), [megaclient.md](megaclient.md) | |
| 4.4 | Версионирование сервисов | Канонический DX: `->service()->v3()->resource()->method()`. Fallback запрещён в explicit-режиме. | [provider-methodology.md#35](provider-methodology.md#35-версионирование-сервисов), [versioning.md](versioning.md) | |
| 4.5 | Laravel: namespaced config | Для provider SDK рекомендуется `config/apisutra/<provider>.php`, чтение `config('apisutra.<provider>.*')`. Совместимость со старыми ключами нужна только при миграции существующих потребителей; порядок чтения и условие удаления адаптера описаны в руководстве. | [laravel.md](laravel.md#рекомендация-laravel-конфиги-для-provider-sdk) | |

---

## Блок 5: Ошибки, результаты, пагинация

| № | Пункт | Пояснение / подсказка | Ссылка на детали | Мои заметки |
|---|-------|------------------------|------------------|-------------|
| 5.1 | Ошибки и статусы | StatusMap (match, без логики в запросах), ClientErrorMapperInterface, ErrorContextFactory при необходимости. Глобальные vs локальные коды — не смешивать. | [provider-methodology.md#4](provider-methodology.md#4-ошибки-и-статусы), [errors.md](errors.md) | |
| 5.2 | ResultMetaExtractor | Envelope-поля (resultCode, resultMessage, operationToken, requestId) — не в каждый DTO. Извлекать централизованно. Extractor не I/O, не бросает при отсутствии меты. | [provider-methodology.md#provider-resultmetaextractor](provider-methodology.md#provider-resultmetaextractor-настоятельная-рекомендация-для-envelope-техполей), [client-config/responses-errors.md](client-config/responses-errors.md) | |
| 5.3 | Static provider catalogs | Для pricing/capabilities/operation descriptors/dictionaries используйте read-only catalog layer (`ProviderCatalogInterface`, `ProviderCatalogRegistryInterface`), а не runtime `ExecutionResult->meta`. Без I/O. `generatedAt` обязателен. Не смешивайте catalog layer с `OperationDescriptor`: это отдельный request-level metadata механизм. | [provider-catalogs.md](provider-catalogs.md), [provider-methodology.md#static-provider-catalogs](provider-methodology.md#static-provider-catalogs), [attributes/request.md](attributes/request.md) | |
| 5.4 | Operation Inventory | Если нужен единый read-only список всех request-операций SDK, используйте `OperationInventory`, а не ручной provider-side агрегатор. Это introspection-layer поверх `RequestScanner` + `RequestSpecResolver`, не связанный с runtime meta и не равный provider catalogs. | [operation-inventory.md](operation-inventory.md), [provider-methodology.md#operation-inventory](provider-methodology.md#operation-inventory) | |
| 5.5 | Branded ResolvedResult | Нужен при envelope, provider-specific meta, нескольких форматах ответов, переинтерпретации статуса. Если «достаёте» данные через result()->meta — сигнал к branded result. | [provider-methodology.md#когда-создавать-branded-resolvedresult](provider-methodology.md#когда-создавать-branded-resolvedresult) | |
| 5.6 | Continuation token | Long-running (operationToken/taskId/jobId) — единый extractor в ClientConfig. Не resource-level helper на каждый ресурс. | [provider-methodology.md#continuation-token](provider-methodology.md#continuation-token-для-long-running-сценариев), [continuation-token.md](continuation-token.md) | |
| 5.7 | providerTraceId | **Только** при подтверждённой поддержке трассировки провайдером. Документация, реальные ответы, контракт. Без подтверждения — не вводить. | [provider-methodology.md#providertraceid](provider-methodology.md#providertraceid--errorprovidertraceid) | |
| 5.8 | Пагинация | PaginationConfig, PaginationMetaResolver. >2–3 paginated endpoints — типизированные коллекции, DTO-контейнер AbstractPaginationContainerDto. | [provider-methodology.md#5](provider-methodology.md#5-пагинация-как-стандарт), [pagination.md](pagination.md) | |
| 5.9 | appCode | Для универсального SDK обычно не нужен. Оставляйте `appCode = null`, развивайте `clientCode`. Маппинг `clientCode -> appCode` — в приложении. Только при домен-специфичном пакете. | [provider-methodology.md#когда-вводить-appcode](provider-methodology.md#когда-вводить-appcode-и-когда-не-нужно) | |
| 5.10 | Provider Async Await | Optional sync/async через один метод — `#[ContinuationResult]`, `ContinuationMode`, `await()/awaitAs()`, `ContinuationModeApplicatorInterface`. Один request на бизнес-действие. | [provider-async-await.md](provider-async-await.md) | |

---

## Блок 6: Тестирование

Практическое правило для этого блока:
- `опционально` здесь означает “не часть обязательного core-контракта”, а не “почти никогда не нужно”;
- для production SDK на ApiSutra многие live/testing-паттерны оказываются востребованными регулярно;
- решение лучше принимать по признакам API: платность, async-flow, файлы, rate-limit, нестабильность, отсутствие sandbox, Laravel-интеграция.

| № | Пункт | Пояснение / подсказка | Ссылка на детали | Мои заметки |
|---|-------|------------------------|------------------|-------------|
| 6.1 | Базовый тестовый минимум | Сериализация/гидрация DTO, маппинг ошибок и статусов. Пагинация, meta, batch, async/polling, files и Laravel добавляются по фактическому использованию этих возможностей. | [provider-methodology.md#6](provider-methodology.md#6-минимальный-набор-тестов), [testing.md](testing.md) | |
| 6.2 | Что часто становится baseline | `record/playback` обычно окупается для платных, медленных, лимитированных и недетерминированных API. `RequestContractTestHelper` почти всегда нужен, если SDK использует `RequestOneOf` / `RequestDiscriminator`. | [provider-methodology.md#6](provider-methodology.md#6-минимальный-набор-тестов), [testing.md](testing.md) | |
| 6.3 | Live-тесты: нужны ли вообще | Live-контур имеет смысл, если нужен реальный контракт, нет нормального sandbox, есть file/async сценарии, платные вызовы или сильная зависимость от реального состояния внешней системы. | [live-testing.md](live-testing.md) | |
| 6.4 | Live-тесты: core baseline | Базовые ApiSutra primitives: `LiveEnvLoader`, `LivePolling`, `LiveResultAssertions`. Они не задают архитектуру live-suite, а дают единые строительные блоки. | [live-testing.md](live-testing.md) | |
| 6.5 | Live-тесты: что рекомендуется по умолчанию | `LiveTestGuard` или эквивалентный env-gating, отдельный `LiveClientFactory` / live builder при особых настройках клиента, группировка live-suite при нескольких сценариях. | [live-testing.md](live-testing.md) | |
| 6.6 | Live-тесты: что включать по условиям | Response dumping, live-cache, negative-suite, balance/cost preflight, Laravel smoke, `LiveFixtureLoader`, Makefile и прочий операторский DX добавляйте по признакам API и масштабу SDK. | [live-testing.md](live-testing.md) | |

---

## Краткий итоговый чеклист (для финальной проверки)

- [ ] Единые enums и DTO
- [ ] Базовые классы определены и используются
- [ ] Запросы и DTO наследуют от base
- [ ] ClientConfig через фабрику/отдельный класс
- [ ] Повторяющиеся тела вынесены в DTO
- [ ] DTO/Enums не лежат плоской свалкой в одной папке
- [ ] Протокольные нюансы зафиксированы
- [ ] DependsOnRequest при подготовительных запросах
- [ ] Provider credentials в ClientConfig
- [ ] Глобальные политики в ClientConfig
- [ ] EnumSerialization задан явно (настоятельная рекомендация)
- [ ] Rate Limit и Retry настроены
- [ ] Endpoint-особенности в атрибутах (в т.ч. RequestDefaults, BodyRoot)
- [ ] Хуки при необходимости (trace-id, логирование)
- [ ] Файлы и архивы при upload/download
- [ ] Laravel: namespaced config `config/apisutra/<provider>.php` (при Laravel-интеграции)
- [ ] Аутентификация оформлена
- [ ] Sandbox/тестирование продумано
- [ ] Мультисервисность/версионирование учтены при необходимости
- [ ] Ошибки, StatusMap, ErrorContext
- [ ] Static provider catalogs отделены от runtime meta
- [ ] Operation Inventory используется вместо ручного агрегатора операций
- [ ] appCode не введён без необходимости
- [ ] ResultMetaExtractor при envelope
- [ ] Branded ResolvedResult при необходимости
- [ ] Continuation token при long-running
- [ ] Provider Async Await при optional sync/async
- [ ] providerTraceId только при подтверждённой поддержке
- [ ] Пагинация оформлена
- [ ] Тесты покрывают сериализацию, ошибки, пагинацию, batch при необходимости
- [ ] Live-тесты организованы при необходимости (env-gating, кеш, дампы, Makefile)
