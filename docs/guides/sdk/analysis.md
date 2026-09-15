# Исследование внешнего API

Перед реализацией провайдера зафиксируйте ключевые решения — это снизит число
переписываний и упростит поддержку.

## Базовые параметры
- baseUrl и версии API
- обязательные заголовки и общие параметры
- формат ответов (JSON, XML, файлы)
- есть ли несколько **разных** API‑сервисов с отдельными baseUrl/auth
- различаются ли глобальные настройки (baseUrl/auth/pagination/serialization)

## Аутентификация
- тип (API key, OAuth, HMAC)
- нужна ли ротация/refresh
- есть ли разные уровни доступа (scope)
- где передаётся токен (header/query), нужен ли только для части запросов
- есть ли подготовительные/системные запросы и зависимости между шагами
- как обрабатываются 401/403 и сколько попыток допустимо

Рекомендация: выносите auth‑логику в классы (`AuthenticatorInterface`,
`AuthPolicyInterface`) и передавайте их в `ClientConfig`, чтобы конфиг оставался тонким.

Сопоставление: `ClientConfig.auth`, `authScopes`, `AuthPolicy`, `AuthScope`.

## Пагинация
- тип: offset или cursor
- где хранится meta и items
- лимиты по страницам
- нужны ли свои классы: `PaginationMetaResolver`, коллекция items, DTO‑контейнер

Сопоставление: `PaginationConfig`, `#[Pagination]`, `PaginationRule`.

## Rate Limit и Retry
- лимиты по ключам и окнам (per‑token/per‑endpoint)
- поведение при превышении (wait/throw)
- какие статусы и исключения безопасно повторять

Сопоставление: `RateLimitConfig`, `RetryConfig`, `#[RateLimit]`, `#[Retry]`.

## Ошибки и маппинг
- структура ошибок провайдера
- единая модель ошибок в SDK
- нужен ли типизированный контекст ошибок (traceId/target/hint); **`providerTraceId`** — только
  при подтверждённой поддержке трассировки провайдером (см. [контракт метаданных](../../reference/results/handles.md#provider-resultmetaextractor))

Сопоставление: `ClientErrorMapperInterface`, `ErrorContextFactoryInterface`, `ResolvedResultFactoryInterface`.

## DTO и сериализация
- naming strategy
- типы/форматы (даты, деньги, enums)
- требования к валидации

Сопоставление: [атрибутные профили](../../reference/dto/profiles.md) или
[внешние правила](../../reference/dto/field-rules.md). `ClientConfig.casts` действует
при сериализации; registry не подключает casts гидратации.

## Файлы и архивы
- загрузка файлов (multipart/binary/base64)
- скачивание файлов и архивов

Сопоставление: `#[File]`, `#[Download]`, `ArchiveExtension`, `ArchiveConfig`.

## Структура, DTO и enums
- структура папок и владение сущностями (ресурсы → запросы/DTO/enum)
- провайдер‑wide типы в `Domain/Dto` и `Domain/Enums`
- группировка DTO/enum по подпапкам при большом объёме
- базовые абстракции (BaseRequest/BaseDto/BaseResource)

Сопоставление: [Методология провайдера](../../start/create-sdk.md).

## Пример чек‑листа анализа
- [ ] baseUrl, версии API, отдельные домены
- [ ] несколько сервисов и различия глобальных настроек
- [ ] auth: тип, scopes, refresh, 401/403
- [ ] pagination: тип, meta/items, лимиты, нестандартные поля
- [ ] rate‑limit/retry: лимиты, коды/исключения
- [ ] ошибки: формат и маппинг
- [ ] DTO/serialization: naming, типы, касты, валидация
- [ ] файлы/архивы: upload/download
- [ ] структура SDK: ресурсы, DTO/enum, Domain‑слой, базовые абстракции
- [ ] sandbox/тестирование: baseUrl, креды, ограничения

## Итоговый артефакт

Сохраните карту решений SDK со ссылками на версию API и фикстуры. Для каждого
решения укажите подтверждённый факт, неизвестное условие и способ проверки.
Настройки выбираются в [каталоге ClientConfig](../../reference/client/configuration.md),
декларации — в [справочнике атрибутов](../../reference/attributes/README.md).
Мультисервисность разобрана в [отдельном рецепте](../integration/multi-service.md).

Provider credentials задаются через [credentialsConfig](../../reference/auth/credentials.md).
Исключения конкретной операции и oneOf-контракты описываются на запросе:
[RequestDefaults](../../reference/attributes/request.md#requestdefaults),
[BodyRoot](../../reference/attributes/request.md#bodyroot),
[RequestOneOf](../../reference/attributes/request.md#requestoneof) и
[RequestDiscriminator](../../reference/attributes/request.md#requestdiscriminator).
Проверяйте их до отправки через [RequestContractTestHelper](../../reference/testing/mocking.md).

## Нюансы протокола
До начала разработки зафиксируйте протокольные нюансы — они влияют на структуру кода:
- где передаётся auth (header/query) и нужен ли он только для части запросов
- есть ли подготовительные/системные запросы и зависимости «ключ → данные»
- требуются ли особые форматы query (например, массив → строка)
- есть ли подстановки в URL (path‑параметры)
- есть ли полиморфные массивы в ответах (`items[]` с разными типами элементов)

Под это заранее выбираются механизмы: `authScopes`/`AuthScope`, `QueryArrayFormat`,
`#[Path]`, `DependsOnRequestInterface` и правила сериализации.
Если провайдер ожидает нестандартный формат (например, массив как строку),
фиксируйте это заранее и см. [Сериализация запросов](../../reference/serialization/request-parts.md).

Рекомендация: перед разработкой DTO обязательно сделайте предварительный анализ
ответов на предмет полиморфных массивов. Это позволяет заранее выбрать правильную
стратегию моделирования:
- обычный `array` или `RawCollection` для гибких mixed‑структур;
- типизированная коллекция, если есть общий контракт элемента;
- `Nested` с полиморфной гидрацией (`discriminator`/`map`, режим `Value` или `Key`)
  и явной политикой для неизвестных вариантов.
Подробности: [DTO](../dto/attribute-models.md), [Data Transfer attributes](../../reference/attributes/hydration.md),
[Коллекции](../../reference/dto/collections.md).
