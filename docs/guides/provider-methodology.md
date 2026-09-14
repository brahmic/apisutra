# Методология разработки провайдера

Цель — получить предсказуемую структуру SDK‑клиента с **минимальным** бойлерплейтом,
максимально используя возможности пакета, его паттерны и современные стандарты
кодирования — без рассинхрона между запросами, DTO и политиками выполнения.

## Входной артефакт
Начинайте с карты решений (см. [Анализ провайдера](./provider-analysis.md)).
Дальше используйте эту карту как источник требований для структуры SDK.
Для пошаговой работы используйте [Чеклист разработки провайдера](./provider-checklist.md).

## 1) Карта решений → единая модель
Зафиксируйте и **централизуйте** то, что должно быть единым:
- enums для ключей операций, статусов, типов сущностей и ошибок
- модель ошибок и правила маппинга в SDK
- стандартизированные DTO и форматы полей
- базовые правила пагинации и меты
- логическую группировку запросов по ресурсам

Рекомендация: **сразу** добавляйте `title()` во все enum, которые участвуют в ответах/запросах.
Это упрощает UX, делает сериализацию предсказуемой и исключает доработки при включении
`DtoSerializationProfile` и request-level enum policy.
`title()` должен возвращать человекочитаемое название **текущего** значения enum.

**Рекомендация:** централизуйте правила по направлениям:
- hydration DTO — через `DtoHydrationProfile` либо [внешний `HydrationRules`](hydration-rules.md) для моделей без атрибутов
- DX DTO serialization — через `DtoSerializationProfile`
- wire body semantics — через `ClientConfig::wireBodySerializationPolicy`
- request/query/header/path semantics — через `ClientConfig`
Для body DTO рекомендуемый default:
- `enumOutput: EnumOutput::TitleValueString`
- `strictEnums: false`

Это даёт читаемый формат `title|value`, а при отсутствии `title()` использует безопасный
fallback без жёсткой ломки SDK.

Практический шаблон:
```php
final readonly class ProviderDtoSerializationProfile implements DtoSerializationProfileInterface
{
    public function policy(): DtoSerializationPolicy
    {
        return new DtoSerializationPolicy(
            enumOutput: EnumOutput::TitleValueString,
            strictEnums: false,
            namingStrategy: NamingStrategy::SnakeCase,
            serializeNulls: false,
        );
    }

    public function casts(): array
    {
        return [];
    }
}
```

## 2) Базовые классы вместо копипаста
Создайте свои base‑классы, которые задают дефолты и правила поведения.
Это основная точка снижения бойлерплейта.

```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use Brahmic\ApiSutra\Core\AbstractClient;

final class ProviderClient extends AbstractClient
{
    public function __construct(TransportInterface $transport)
    {
        parent::__construct(
            new ClientConfig(
                baseUrl: 'https://api.example',
                dtoSerializationProfile: new ProviderDtoSerializationProfile(),
                throwOnErrors: false,
            ),
            $transport,
        );
    }
}
```

Рекомендуемые базовые классы для атрибутной модели SDK:
- `BaseClient` — дефолтный `ClientConfig`, общие политики
- `BaseRequest` — общие правила сериализации/валидации/опций
- `BaseResource` — единая навигация и группировка запросов
- `BaseDto` — общий формат для входных DTO и binding `DtoHydrationProfile` / `DtoSerializationProfile`
- `BaseResponseDto` — единые соглашения по DTO‑ответам и binding `DtoHydrationProfile` / `DtoSerializationProfile`

### Рекомендация

Сначала создайте свои базовые абстракции от `AbstractRequest`/`AbstractDto` и т.д.,
а затем наследуйте конкретные запросы и DTO уже от этих базовых классов.
Так вы централизуете кастомные задачи и правила в одном месте,
без правок десятков классов.

Для атрибутных DTO рекомендуемый pattern:
- `BaseDto` / `BaseResponseDto` привязаны к `DtoHydrationProfile`
- `BaseDto` / `BaseResponseDto` привязаны к `DtoSerializationProfile`
- `ClientConfigFactory` при необходимости передаёт профиль сериализации в `ClientConfig::dtoSerializationProfile`; входящий профиль остаётся привязан к классу
- конкретные DTO обычно не размечаются дополнительно
- class-level `#[DtoHydrate(...)]` / `#[DtoSerialize(...)]` используются только для редких override

Для plain-моделей базовые DTO ApiSutra и атрибуты не нужны. Соберите один
`HydrationRules` в factory, передайте его в `ClientConfig::hydrationRules`, а вне
клиента — в `Hydrator::forRules()`. `DTO::from()` набор не наследует. Не совмещайте
`DtoRules` с профилем гидратации одного класса; [правила конфликтов](hydration-rules.md#правила-и-проверка-конфигурации)
проверяются при создании гидратора. Рекомендуемое имя приёмника неизвестных полей —
`_extra`; клиент с набором исключает его из запросов.

Допустимый pattern для DTO inheritance:
- базовый DTO держит constructor-backed общие поля
- конечный DTO может добавлять public hydrated properties без собственного конструктора
- hydrator поддерживает такой shape через constructor-first + fallback assignment remaining properties

Если провайдер системно использует `''` как "значения нет", не нормализуйте это вручную по всем DTO.
Используйте:
- property-level `EmptyStringAsNull` для точечных случаев
- hydration profile-level `emptyStringBehavior` для централизованной политики

Подробности и примеры: [DTO](./dto.md).

Если одно и то же тело запроса используется в нескольких запросах — вынесите его в DTO
и используйте в запросах как свойство (`Body DTO`). См. [Requests](./requests.md).

Если у провайдера много эндпоинтов, заранее разложите/сгруппируйте по логическому признаку их по ресурсам —
это улучшит навигацию и сделает API клиента читаемым. См. [Resources](./resources.md).

## 2.1) Структура и владение сущностями

Этот раздел описывает SDK конкретного провайдера. Структура внутренних модулей
ядра ApiSutra определяется их механизмами и контрактами.

### Обязательные границы владения

- Запрос и его собственные DTO/enum принадлежат одному методу ресурса и хранятся вместе.
- Типы, используемые несколькими методами одного ресурса, остаются в этом ресурсе.
- `Domain/Dto` и `Domain/Enums` предназначены для типов уровня всего провайдера.
  Не превращайте общий слой в склад типов без определённого владельца.
- `Client` отвечает за сборку настроек и точку входа. Auth, pagination и error mapping
  остаются отдельными механизмами, которые передаются в конфигурацию.

### Каноническая структура endpoint (настоятельная рекомендация)

Предпочитайте группировку вокруг запроса. Создавайте только те каталоги и базовые
классы, которые нужны используемым возможностям SDK:

```text
Base/
  BaseClient.php
  BaseRequest.php
  BaseResource.php
  BaseDto.php
  BaseResponseDto.php
Client/
  ProviderClient.php
  ProviderClientConfig.php
Auth/
Pagination/
Errors/
Domain/
  Dto/
  Enums/
Resources/
  Users/
    UsersResource.php
    Common/
      Dto/
      Enums/
    Requests/
      ListUsers/
        ListUsersRequest.php
        Dto/
          Request/
          Response/
        Enums/
tests/
  Unit/
    Resources/
      Users/
        Requests/
          ListUsers/
            ListUsersTest.php
```

`Common/` нужен только для типов, используемых несколькими методами ресурса.
В корне ресурса остаются сам ресурс и общие для него компоненты.

Не разносите один метод по параллельным деревьям вроде
`Requests/ListUsersRequest.php` и `ListUsers/Dto/`: запрос, его DTO и enum
должны оставаться в одном поддереве `Requests/ListUsers/`.

### Рекомендации для больших моделей

- Для небольшого ресурса допустима простая структура с небольшим числом DTO.
- Когда файлов становится много, группируйте их по endpoint, сценарию или
  смысловым блокам ответа. То же относится к enum.
- Для сложного ответа внутри `Requests/<Method>/Dto/Response/` можно выделить,
  например, `Main/`, `Rights/`, `Bounds/`, `History/`, `Restrictions/`.
- Подгруппы ресурсов вводите по бизнес-контексту и навигации API клиента.
  Глубина вложенности и число файлов сами по себе не требуют новых слоёв.

О навигации между ресурсами и привязке запросов к клиенту см. [Resources](./resources.md).

## 3) Где задавать правила
Распределяйте правила по уровню ответственности:
- `ClientConfig` — глобальные дефолты (retry, rate‑limit, cache, timeouts)
- `DtoHydrationProfile` — входные правила атрибутных DTO (`from()` и pipeline)
- `HydrationRules` — входные правила plain DTO, strict, формы и остаток данных; [границы исходящих запросов](hydration-rules.md#receiver-в-исходящих-запросах)
- `DtoSerializationProfile` — DX DTO semantics (`toArray()`, enum output, DTO naming, null policy)
- `ClientConfig::wireBodySerializationPolicy` — transport body semantics
- атрибуты запроса — специфика конкретного endpoint
- runtime‑опции — разовые переопределения на вызов

Практическое правило:
- DTO hydration contract задаётся через `DtoHydrationProfile` либо внешний `HydrationRules`
- DX DTO contract задаётся через `DtoSerializationProfile`
- wire body contract задаётся через `ClientConfig::wireBodySerializationPolicy`
- request/query/header/path contract задаётся через `ClientConfig`
- профиль сериализации можно передавать в `ClientConfig`; набор гидратации передавайте явно клиенту и standalone-гидратору

Если провайдер требует служебные креды в `body/query/form`, задавайте их
централизованно через `ClientConfig::credentialsConfig`.
Это устраняет копипасту по request-классам и делает merge‑политику детерминированной.

## 3.0) Provider credentials: рекомендуемый подход
Что фиксировать на уровне провайдера:
- defaults для `body/query/form`
- scopes (обычно синхронно с `authScopes`)
- merge mode (`fill-missing` как безопасный default)
- `secretKeys` для redaction в debug

Практика:
- request-класс описывает только endpoint‑специфику
- provider‑wide креды живут в `ClientConfig`
- исключения решаются через:
  - `#[SkipCredentialsEnrichment]`
  - runtime `withCredentialsEnrichment()/withCredentialsMergeMode()/withCredentialsScope()`

Где детали:
- [ClientConfig: Auth (граница Auth vs Credentials Enrichment)](./client-config/auth.md)
- [Сериализация запросов (этап enrichment и merge-policy)](./serialization.md)
- [ClientConfig: Observability (debug/redaction/secretKeys)](./client-config/observability.md)
- [Запросы (runtime-override API и usage)](./requests.md)

Если request-класс содержит много body-полей, рекомендуется class-level
`RequestDefaults` (а property-атрибуты использовать только для override).
Полный контракт: [Request attributes: RequestDefaults](./attributes/request.md#requestdefaults).

Если endpoint требует root-body в формате `[...]` (JSON Patch / bulk),
используйте `BodyRoot` вместо `beforeSend`-перезаписи payload.
Полный контракт: [Request attributes: BodyRoot](./attributes/request.md#bodyroot).

Если endpoint имеет взаимоисключающие payload-варианты (`oneOf`), используйте
`RequestOneOf` + `RequestDiscriminator` как основной и рекомендуемый подход.
Это убирает ручные `assert...()` и стабилизирует DX/ошибки между ресурсами.
Полный контракт: [Request attributes: RequestOneOf](./attributes/request.md#requestoneof).

### Рекомендуемые паттерны для `RequestOneOf`/`RequestDiscriminator`
- Для строго взаимоисключающих payload используйте `OneOfMode::ExactlyOne`.
- Для «мягких» контрактов (допускается несколько вариантов) используйте `OneOfMode::AtLeastOne`.
- При наличии discriminator всегда задавайте `map` так, чтобы одно значение указывало на один вариант.
- Для вложенных payload используйте dot-path (`payload.signature.id`) вместо ручной валидации в request-классе.
- Держите `requiredCommon` минимальным: только действительно обязательные поля для всех вариантов.
- Поля вариантов именуйте доменно (`emailPayload`, `phonePayload`), чтобы ошибки `RequestContractViolation` читались без расшифровки.
- Для тестов SDK используйте `RequestContractTestHelper`, чтобы не дублировать парсинг `violations` в каждом тесте.

Подробности: [ClientConfig](./client-config/README.md) и [Attributes](./attributes/README.md).

## 3.1) Нюансы протокола (фиксируйте заранее)
До начала разработки зафиксируйте протокольные нюансы — они влияют на структуру кода:
- где передаётся auth (header/query) и нужен ли он только для части запросов
- есть ли подготовительные/системные запросы и зависимости «ключ → данные»
- требуются ли особые форматы query (например, массив → строка)
- есть ли подстановки в URL (path‑параметры)
- есть ли полиморфные массивы в ответах (`items[]` с разными типами элементов)

Под это заранее выбираются механизмы: `authScopes`/`AuthScope`, `QueryArrayFormat`,
`#[Path]`, `DependsOnRequestInterface` и правила сериализации.
Если провайдер ожидает нестандартный формат (например, массив как строку),
фиксируйте это заранее и см. [Сериализация запросов](./serialization.md).

Рекомендация: перед разработкой DTO обязательно сделайте предварительный анализ
ответов на предмет полиморфных массивов. Это позволяет заранее выбрать правильную
стратегию моделирования:
- обычный `array` или `RawCollection` для гибких mixed‑структур;
- типизированная коллекция, если есть общий контракт элемента;
- `Nested` с полиморфной гидрацией (`discriminator`/`map`, режим `Value` или `Key`)
  и явной политикой для неизвестных вариантов.
Подробности: [DTO](./dto.md), [Data Transfer attributes](./attributes/data-transfer.md),
[Коллекции](./collections.md).

## 3.2) Аутентификация (детализация)
Перед реализацией зафиксируйте:
- тип и формат auth (header/query), ключи заголовков и параметры
- есть ли разные уровни доступа и какие запросы требуют auth
- нужна ли ротация/refresh, как и где получать токен
- есть ли системные/подготовительные запросы и зависимость «ключ → данные»
- как обрабатывать 401/403 и какие попытки допустимы

Рекомендация: оформляйте auth‑логику в классах (`AuthenticatorInterface`,
`AuthPolicyInterface`) и передавайте их в `ClientConfig`, чтобы конфиг оставался тонким.
Подробности: [Аутентификация](./auth.md) и [ClientConfig: Auth](./client-config/auth.md).

## 3.3) Sandbox и тестирование API
До старта разработки выясните:
- есть ли у провайдера sandbox/тестовый стенд и отдельный baseUrl
- какие тестовые ключи/креды доступны и как они отличаются от production
- есть ли ограничения по rate‑limit для sandbox
- есть ли официальные примеры/фикстуры/статические ответы

Это важно, чтобы заранее продумать конфигурацию и архитектуру:
- разделить `ClientConfig` для prod/sandbox
- подготовить стратегию моков/фикстур (`MockTransport`, record/playback)
- заложить точки расширения для тестов (hooks/extension)

Подробности: [Тестирование](./testing.md).

## 3.4) Мультисервисность и мегаклиент
На этапе анализа провайдера уточните:
- предоставляет ли поставщик **несколько** API‑сервисов
- есть ли разные baseUrl, ключи/способы auth, правила пагинации

Критерий мегаклиента — **различие глобальных настроек**
(baseUrl/auth/pagination/serialization).  
Если различий нет — используйте один клиент + ресурсы.
Если различия есть — уместно мегаклиент и отдельные конфиги по сервисам.
Подробности: [Мегаклиент](./megaclient.md)

## 3.5) Версионирование сервисов
Рекомендуемый DX:
- canonical: `->service()->v3()->resource()->method()`
- альтернатива: `->service()->useVersion(ServiceVersion::V3)->resource()->method()`

Допускается переходный `compat-default`, когда default‑ветка
маршрутизирует разные методы в разные версии.
В explicit‑режиме (`vX()`/`useVersion(...)`) fallback должен быть запрещён,
а неподдерживаемый маршрут завершаться `UnsupportedVersionException`.

Подробности: [Версионирование сервисов](./versioning.md).

## 4) Ошибки и статусы
Сделайте единый маппинг ошибок и статусов:
- входные коды провайдера → единая модель SDK
- для статусов long‑running процесса — собственный enum + правила интерпретации

Если у провайдера есть одновременно глобальные коды и локальные коды конкретных методов,
зафиксируйте это как обязательный архитектурный инвариант:
- **не смешивайте** глобальные и локальные коды в одном enum/registry;
- глобальные коды держите отдельно (provider‑wide слой);
- локальные коды храните рядом с соответствующим request-методом и интерпретируйте только в его контексте.

Рекомендуемая структура:
- `Errors/Enums/*` — только глобальные provider‑wide коды;
- `Resources/<Resource>/Requests/<Method>/Enums/*` (или эквивалент рядом с request) —
  только локальные коды этого метода.

Смешивание этих слоёв приводит к ложной унификации, коллизиям значений и ошибочному
маппингу `providerCode -> clientCode`.

Точки интеграции: `ClientErrorMapperInterface`, `ResolvedResultFactoryInterface`,
гайд [Ошибки и результаты](./errors.md).

### Provider ResultMetaExtractor (настоятельная рекомендация для envelope-техполей)
Если провайдер возвращает технические поля (`resultCode`, `resultMessage`, `operationToken`,
`requestId` и т.д.) в обычных (не paginated) ответах:
- не дублируйте эти поля в каждом endpoint DTO;
- оставляйте DTO только с бизнес-данными;
- извлекайте техмету централизованно через `ClientConfig::resultMetaExtractor`.

Минимальный паттерн:
```php
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\ResultMeta;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\Result\ResultMetaExtractorInterface;

final readonly class ProviderEnvelopeMeta implements ResultMeta
{
    public function __construct(
        public ?int $resultCode,
        public ?string $resultMessage,
        public ?string $operationToken,
    ) {}
}

final class ProviderResultMetaExtractor implements ResultMetaExtractorInterface
{
    public function extract(ExecutionResult $result): ?ResultMeta
    {
        $payload = $result->response?->json()
            ?? $result->errors->first()?->response?->json();

        if (!is_array($payload)) {
            return null;
        }

        return new ProviderEnvelopeMeta(
            resultCode: $payload['resultCode'] ?? null,
            resultMessage: $payload['resultMessage'] ?? null,
            operationToken: $payload['operationToken'] ?? null,
        );
    }
}
```

Инварианты:
- extractor не должен выполнять I/O и не должен бросать исключение для сценария "мета отсутствует";
- если `ExecutionResult->meta` уже заполнена (pagination/batch/composite), она не перезаписывается;
- если часть endpoint не возвращает техмету, extractor возвращает `null` без побочных эффектов.
- extractor должен опираться на `ExecutionResult->response`, а не на `debug`, чтобы
  работать одинаково при `debug=false` и `debug=true`.

### Static provider catalogs
Если SDK должен отдавать заранее подготовленные статические справочники
(pricing/capabilities/operation descriptors/dictionaries), не смешивайте их
с runtime meta результата.

Рекомендуемый слой:
- `ProviderCatalogInterface`
- `RequestBoundProviderCatalogInterface`
- `ProviderCatalogRegistryInterface`
- `ClientConfig::providerCatalogRegistry`

`OperationDescriptor` и provider catalogs — разные механизмы.
`OperationDescriptor` остаётся локальной metadata одного request-класса,
а provider catalogs — отдельным агрегированным read-only слоем SDK.

Инварианты:
- каталог read-only
- каталог не делает I/O
- каталог не зависит от `ExecutionResult` и transport pipeline
- `generatedAt` — обязательная часть меты каталога

Подробности и пример: [Provider Catalogs](./provider-catalogs.md).

### Operation Inventory
Если нужен единый read-only снимок всех request-операций SDK
(requestClass/method/endpoint/responseType/descriptor flags), используйте
`OperationInventory`, а не ручные provider-side агрегаторы.

Это отдельный introspection-layer поверх request-классов:
- строится через `RequestScanner` + `RequestSpecResolver`
- не зависит от transport/pipeline/runtime result
- не заменяет provider catalogs

Подробности: [Operation Inventory](./operation-inventory.md).

### Когда создавать branded ResolvedResult

**Создавайте branded result** (собственную реализацию `ResolvedResultInterface`), когда:

- Провайдер оборачивает ответы в техническую обёртку (envelope с полями `resultCode`, `resultMessage`, `operationToken`, `requestId`). Branded result отделяет техмету от бизнес-данных и предоставляет типизированный `meta()`.
- Нужен прямой доступ к provider-specific meta без обращения к низкоуровневому `result()->meta`. Примеры: pagination meta (`total`, `lastPage`), оставшаяся квота запросов, время до сброса rate limit, идентификатор транзакции.
- Провайдер возвращает несколько форматов ответов (paginated list, non-paginated list, flat object, custom wrapper) — branded result унифицирует доступ к данным и мете, скрывая различия форматов от потребителя SDK.
- Нужна переинтерпретация статуса. Например, провайдер возвращает «нет данных» как ошибку (HTTP 404 или специальный код в body), но для бизнес-логики это валидный успешный результат. Branded result перехватывает `isSuccess()`/`hasErrors()` и нормализует семантику.

**Хватает стандартного ResolvedResult**, когда:

- Нет envelope, нет специфической meta.
- Вся provider-специфика укладывается в `ClientErrorMapperInterface` + `ErrorContextFactoryInterface`.
- Нет потребности в кастомных accessor-методах на уровне результата.

**Правило:** branded result оправдан не только наличием envelope, а любой потребностью в provider-specific DX на уровне результата. Если разработчику SDK приходится «доставать» данные через `result()->meta` или интерпретировать raw-поля — это сигнал к branded result.

### DX‑слой чтения ошибок
В прикладном коде используйте `ResolvedResult`:
```php
$result = $request->send()->resolved();

$code = $result->errorCode();       // приоритет: app → client → provider → sdk
$message = $result->errorMessage(); // человекочитаемое сообщение
$status = $result->errorStatus();   // HTTP‑статус провайдера, если есть

// Для batch/pool/composite:
$views = $result->errorViews();     // список ClientError
```

Это снижает бойлерплейт и делает DX единым, независимо от провайдера.

### Continuation token для long-running сценариев
Если провайдер возвращает токен операции (`operationToken`/`taskId`/`jobId`),
не добавляйте resource-level helper для каждого ресурса.
Подключите единый extractor в `ClientConfig`:

```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Result\ContinuationTokenExtractorInterface;
use Brahmic\ApiSutra\Result\ExecutionResult;

final class ProviderContinuationTokenExtractor implements ContinuationTokenExtractorInterface
{
    public function extract(ExecutionResult $result): ?string
    {
        $data = $result->response?->json();
        if (!is_array($data)) {
            return null;
        }

        $token = $data['operationToken'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }
}

$config = new ClientConfig(
    baseUrl: 'https://api.provider.test',
    continuationTokenExtractor: new ProviderContinuationTokenExtractor(),
);
```

После этого прикладной код всегда одинаковый:
- `$request->send()->resolved()->continuationToken()`
- `$request->send()->resolved()->continuationTokenOrFail()`
- shortcut через `ResultHandle`: `->continuationToken()`

Если метод поддерживает optional async (sync/async через одну бизнес-операцию),
используйте unified async-await контракт:
- `#[ContinuationResult(...)]` на request-классе;
- непустой `unwrap` либо resolver готовности Pending/Ready/Failed;
- `ContinuationMode` + `asProviderSync()/asProviderAsync()/asProviderAuto()`;
- `ContinuationModeApplicatorInterface` для маппинга mode в provider-протокол;
- `await()/awaitAs()` на `ResultHandle`;
- `awaitByToken()/awaitByTokenAs()` на `$client->continuation()`.

Подробности и DX-примеры: [Provider Async Await](./provider-async-await.md).

### Когда вводить `appCode` (и когда не нужно)
`appCode` — это код уровня бизнес‑приложения, а не кода внешнего API.

Вводите `appCode` в SDK только если одновременно верны условия:
- пакет фактически делается под один конкретный продукт/домен;
- словарь бизнес‑ошибок уже закреплён и стабилен;
- потребители SDK ожидают именно доменные коды из этого словаря.

Для универсального провайдерного SDK (типичный случай) `appCode` обычно не нужен:
- оставляйте `appCode = null`;
- развивайте `clientCode` как стабильный SDK‑слой;
- маппинг `clientCode -> appCode` делайте в приложении (ACL/декоратор).

Это важно зафиксировать заранее, чтобы разработчик или AI‑ассистент
не добавлял `appCode` без подтверждённой необходимости.

Если у провайдера есть специфические поля в контексте ошибок (traceId/target/hint),
определите типизированный контекст:
```php
use Brahmic\ApiSutra\VO\Errors\ClientError;
use Brahmic\ApiSutra\VO\Errors\ErrorContextFactoryInterface;

final readonly class ProviderErrorContext
{
    public function __construct(
        public ?string $traceId,
        public ?string $target,
    ) {}
}

final class ProviderErrorContextFactory implements ErrorContextFactoryInterface
{
    public function make(ClientError $error): ?object
    {
        return new ProviderErrorContext(
            traceId: $error->context['traceId'] ?? null,
            target: $error->context['target'] ?? null,
        );
    }
}
```

#### `providerTraceId` / `errorProviderTraceId`

> **Важно:** Реализуйте поддержку **только** при наличии **подтверждённой** информации,
> что провайдер поддерживает трассировку (trace-id в ответах на ошибки).
>
> Источники подтверждения: документация API, реальные ответы при тестах, контракт интеграции.
> Без подтверждения **не вводите** `providerTraceId` в типизированный контекст и модель пакета —
> это создаёт ложные ожидания и ухудшает диагностику.

Системные ключи ядра (используйте при интеграции): `traceId`, `httpStatus`, `requestClass`, `providerCode`.

## 5) Пагинация как стандарт
Сначала зафиксируйте тип пагинации (offset/cursor), параметры и структуру ответа.
Дальше оформите это в отдельной схеме:
- создайте `PaginationConfig` (и при необходимости свой `PaginationMetaResolver`)
- задайте её в `ClientConfig::paginationConfig`
- установите дефолтный `ClientConfig::paginationRule`

Переопределяйте `#[Pagination]` только если endpoint отличается.

Если у провайдера **больше 2–3 paginated‑эндпоинтов**, **настоятельно рекомендуется**
вынести пагинацию в отдельные классы и типизацию, чтобы убрать бойлерплейт:
- свой `PaginationMetaResolverInterface` для нестандартных полей meta
- типизированную коллекцию items (`itemsCollection`/`itemsCollectionFactory`)
- DTO‑контейнер ответа на базе `AbstractPaginationContainerDto`

Подробности: [Пагинация](./pagination.md), [ClientConfig: Pagination](./client-config/pagination.md).

## 5.1) Практики из примеров
Эти принципы помогают держать код аккуратным и уменьшать бойлерплейт:
- **Разделение enum‑кодов**: разные enum для результата и ошибки (или общий, если API так устроен).
- **Глобальные vs локальные error‑коды**: не объединяйте в один enum; глобальные — в provider‑wide слое,
  методные — рядом с request, который их интерпретирует.
- **StatusMap**: отдельный класс маппинга кодов в `ResultStatus`, только `match`, без логики в запросах.
- **Пагинация через типизацию и обёртки**: `AbstractPaginatedRequest`, `PaginationMetaResolverInterface`,
  `itemsType` + `itemsCollectionFactory`, DTO‑контейнер `AbstractPaginationContainerDto`.
- **DTO с глубокой типизацией**: стройте DTO рекурсивно, используйте типизированные коллекции,
  типы дат (например, `DateTimeImmutable`/`Carbon`) и касты. Глубину определяйте контрактом API
  и нужными потребителю данными. При сложной вложенности проверьте читаемость и группировку
  типов; количество уровней само по себе не требует согласования.
- **Scalar auto-cast по declared type**: для обычных scalar DTO-полей (`int`, `float`, `bool`, `string`)
  в режиме Legacy ядро делает safe auto-cast при гидрации. Не дублируйте `IntegerCast`/`FloatCast`/`BooleanCast`
  без необходимости; явный `#[Cast]` оставляйте только для нестандартного provider-формата
  или кастомной логики преобразования. При [Strict](hydration-rules.md#policy-и-строгие-типы)
  числовые строки отклоняются: проверяйте реальные типы JSON до включения режима.
- **Typed collections и default policy**: для non-nullable typed collection ядро уже покрывает
  кейс `missing -> empty collection`. Явный `DefaultValue([])` оставляйте, только если нужно
  отдельно обработать `null` или жёстко зафиксировать это правило в DTO-контракте.
  `DefaultValue(... when: [Null])` не должен ломать built-in fallback для `Missing`.
  Внешний `FieldRule::required()` проверяется раньше и не позволяет заменить отсутствие
  ключа пустой коллекцией.
- **Маппинг через атрибуты**: `#[Map]` используйте для симметричного двустороннего ключа,
  `#[From]`/`#[To]` — когда направления различаются, `#[Cast]` — для преобразования типов.
  Не размазывайте кастомные правила по DTO без договорённостей.
- **AuthPolicyInterface**: auth только для части запросов задаётся централизованно.
- **Path/Query‑параметры**: `#[Path('uuid')]`, `#[Query(name: 'token')]` для нестандартных имён и dot‑ключей.
- **Enum‑параметры**: события/статусы лучше принимать enum‑типом, а не строкой.
- **Download vs JSON**: `#[Download]` для бинарных ответов, отдельные запросы для разных форматов.

## 6) Минимальный набор тестов

### Unit/contract-тесты (mock)
Обязательный минимум почти для любого SDK:
- сериализация/гидрация DTO
- маппинг ошибок и статусов

Практика для production SDK обычно лучше читается не через бинарное “обязательно / опционально”,
а через 4 уровня применимости:
- базовый минимум — почти обязателен;
- рекомендуется по умолчанию — для большинства реальных SDK быстро окупается;
- нужен при конкретных признаках API;
- операторское удобство — не меняет контракт SDK, но заметно упрощает сопровождение.

Добавляйте по мере использования соответствующих возможностей:
- пагинация и meta
- batch/composite
- async/polling сценарии
- file/download ответы
- Laravel/container-интеграция

Что на практике часто становится baseline:
- `record/playback`, если API не бесплатный, медленный, лимитированный или заметно недетерминированный;
- `RequestContractTestHelper`, если SDK использует `RequestOneOf` / `RequestDiscriminator`;
- отдельные contract-тесты на provider-specific ошибки, если error DX является заметной частью SDK.

Подробности: [Тестирование](./testing.md).

### Live-тесты (real-request)
Live-контур нужен не каждому SDK. Обычно он оправдан, если:
- у провайдера нет sandbox или он плохо отражает production-контракт
- запросы платные, file-based или сильно завязаны на реальное состояние внешней системы
- unit/mock-тестов недостаточно для проверки реального контракта

Минимальный рекомендуемый live-baseline:
- env-gating: явный opt-in флаг + credentials, без которых тесты пропускаются (skip)
- ApiSutra хелперы: `LiveEnvLoader::loadForTests()` (загрузка .env), `LivePolling::waitUntil()` (polling), `LiveResultAssertions::assertSuccess()` / `assertDataInstanceOf()`

Что для многих production SDK уже стоит считать рекомендуемым по умолчанию:
- provider-side `LiveTestGuard` или эквивалентная skip-логика;
- отдельный `LiveClientFactory` / live client builder, если live-клиент требует особых timeout/cache/debug настроек;
- группировка live-suite, если сценариев больше нескольких;
- использование встроенных helper-методов ApiSutra для единообразного live DX между пакетами.

Что включается по явным признакам API:
- `LivePolling::waitUntil()` — если есть async-job, status endpoint или eventual consistency;
- response dumping — если есть sync + async flow, file/download сценарии, слабая внешняя документация или сложные negative cases;
- live-cache — если запросы дорогие, медленные, лимитированные или повторяются при отладке;
- negative live-suite — если error-handling критичен для DX и есть дешёвые безопасные негативные сценарии;
- cost/balance preflight — если API платный или чувствителен к лишним вызовам;
- Laravel smoke — если пакет поставляет service provider, container bindings или config integration.

Опциональные provider-side паттерны:
- группировка по характеру выполнения: sync, async, negative
- файловый кеш с длинным TTL для дорогих или повторяемых вызовов
- сохранение ответов в файлы для анализа
- Makefile-обвязка для ручного запуска
- запрет автозапуска в CI для production-like / платных live-сценариев

Подробности: [Live-тестирование](./live-testing.md).

## Итоговый чек‑лист
См. также [Чеклист разработки провайдера](./provider-checklist.md) — дорожная карта с пояснениями и ссылками.

- есть единые enums и DTO для всех запросов
- базовые классы определены и используются
- запросы и DTO наследуются от своих базовых абстракций
- повторяющиеся тела запросов вынесены в DTO
- протокольные нюансы зафиксированы до разработки
- глобальные политики живут в `ClientConfig`
- все endpoint‑особенности оформлены атрибутами
- тесты покрывают сериализацию, ошибки и пагинацию
- live-тесты организованы при необходимости (env-gating, кеш, дампы)
