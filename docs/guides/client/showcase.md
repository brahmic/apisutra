# Возможности клиента на одном примере

Создадим клиент вымышленного API записей и настроим его под разные задачи:
авторизация, устойчивость к сбоям, кеш, DTO и диагностика. Каждый раздел добавляет
отдельную возможность; сочетайте настройки по требованиям своего API.

[Полный пример](../../example/client-showcase/README.md) выполняет все сценарии
без сети и настоящих credentials. В установленном пакете:

```bash
php vendor/brahmic/apisutra/docs/example/client-showcase/run.php
```

| Задача | Где посмотреть |
| --- | --- |
| Создать подключение и получить типизированный ответ | [Клиент и транспорт](#клиент-и-транспорт) |
| Выбрать credentials для операции | [Аутентификация](#аутентификация) |
| Пережить временную ошибку и соблюдать квоты | [Повторы](#повторы-при-временных-ошибках), [rate-limit](#ограничение-частоты-запросов) |
| Читать повторно без HTTP | [Кеш](#кеш-ответов) |
| Проверять ответ и управлять исходящим DTO | [Правила данных](#dto-на-входе-и-выходе) |
| Найти причину ошибки | [Диагностика](#диагностика-и-ошибки) |
| Настроить другой адрес или один запуск | [Копии и разовые опции](#копии-конфигурации-и-разовые-опции) |

## Клиент и транспорт

[DemoClient](../../example/client-showcase/src/DemoClient.php) наследует `AbstractClient`
и предоставляет ресурс `records()`. У [GetRecordRequest](../../example/client-showcase/src/Resources/Records/Get/GetRecordRequest.php)
атрибуты задают GET `/records/{id}` и `Returns(RecordDto::class, unwrap: 'data')`.
У операции нет собственных retry/cache-настроек, поэтому действуют настройки клиента.

В этом примере транспорт возвращает локальный ответ. Код после его настройки
одинаков и для настоящего HTTP; подключение `HttpTransport` описано в
[standalone-руководстве](../integration/standalone.md#настоящий-http).

```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Transport\MockTransport;
use Example\ClientShowcase\DemoClient;
use Example\ClientShowcase\Resources\Records\Get\GetRecordRequest;
use Example\ClientShowcase\Resources\Records\Save\SaveRecordRequest;

$payload = ['data' => ['id' => 7, 'title' => 'Первая запись', 'future_flag' => true]];
$transport = new MockTransport();
$transport->preventStrayRequests();
$transport->fake([
    GetRecordRequest::class => MockResponse::success($payload),
    SaveRecordRequest::class => MockResponse::success(['saved' => true]),
]);

// 1. Базовое подключение: конфигурация и транспорт передаются отдельно.
$base = new ClientConfig(
    baseUrl: 'https://api.example.test',
    timeout: 15,
    connectTimeout: 3,
);
$client = new DemoClient($base, $transport);
$record = $client->records()->get(7)->send()->dataOrFail();
```

`$record` — типизированный [RecordDto](../../example/client-showcase/src/Resources/Records/RecordDto.php)
с `id = 7` и `title = 'Первая запись'`. Клиент готовит URL
`https://api.example.test/records/7`, таймаут HTTP-запроса 15 секунд и подключения
3 секунды. Mock проверяет передачу этих опций; реальное ожидание сети здесь не моделируется.

Далее используются `$base`, `$payload` и `$transport` из этого раздела.
Фрагменты выполняются после [bootstrap примера](../../example/sdk/bootstrap.php).

## Аутентификация

Передайте стратегию по умолчанию и, при необходимости, именованные стратегии.
В примере все токены вымышленные; приложение подставляет свои credentials.

```php
use Brahmic\ApiSutra\Auth\ApiKeyAuthenticator;
use Brahmic\ApiSutra\Auth\BearerAuthenticator;
use Example\ClientShowcase\DemoClient;

$authenticated = $base->with(
    auth: new BearerAuthenticator('demo-user-token'),
    authScopes: ['service' => new ApiKeyAuthenticator('demo-service-key')],
);
$client = new DemoClient($authenticated, $transport);
$client->records()->get(7)->send()->dataOrFail();
$client->send($client->records()->get(7)->withAuthScope('service'))->dataOrFail();
$client->send($client->records()->get(7)->withoutAuth())->dataOrFail();
```

| Вызов | Что отправится |
| --- | --- |
| Обычный | `Authorization: Bearer demo-user-token` |
| `withAuthScope('service')` | `X-Api-Key: demo-service-key`; выбранная стратегия заменяет основную |
| `withoutAuth()` | Запрос без этих credentials |

Для API с ключом в query выберите альтернативную конфигурацию:

```php
use Brahmic\ApiSutra\Auth\ApiKeyAuthenticator;

$queryAuth = $base->with(auth: new ApiKeyAuthenticator('demo-query-key', header: null, query: 'api_key'));
```

Получится `/records/7?api_key=demo-query-key`. Другие варианты —
[Basic, HMAC и собственная схема](../../reference/auth/strategies.md),
[обновляемые токены](../../reference/auth/tokens.md) и
[credentials в полях запроса](../../reference/auth/credentials.md).
Приоритеты атрибутов, scopes и auth policy находятся в [контракте auth](../../reference/auth/strategies.md).

## Повторы при временных ошибках

Для нашего GET допустим повтор при HTTP 503:

```php
use Brahmic\ApiSutra\Config\RetryConfig;

$resilient = $base->with(
    retry: new RetryConfig(attempts: 2, baseDelay: 0, jitter: false),
);
```

Клиент с этой конфигурацией получает 503, делает вторую попытку и возвращает успех.
`attempts: 2` включает первую попытку. Нулевой интервал нужен быстрому локальному
примеру; для HTTP задайте подходящие `baseDelay`, `maxDelay`, backoff и jitter.
`totalTimeoutMs` ограничивает общий бюджет, включая ожидания и повторы.

Без `retry` повторы по конфигурации выключены. Атрибут операции или runtime-опции
могут задать их отдельно; `withoutRetry()` отключает для одного запуска.
Безопасность повторов POST нужно объявлять по контракту API.
[Параметры, приоритеты и безопасность retry](../../reference/execution/retry.md).

## Ограничение частоты запросов

```php
use Brahmic\ApiSutra\Config\RateLimitConfig;
use Brahmic\ApiSutra\Enums\RateLimiting\RateLimitBehavior;

$limited = $base->with(
    rateLimit: new RateLimitConfig(limit: 2, period: 60, behavior: RateLimitBehavior::Throw),
);
```

Один клиент разрешает два HTTP-запроса за окно 60 секунд. Третий получает
`rate_limited` с причиной `local_rate_limit_exceeded`, до обращения к транспорту.
При обычном `send()` ошибка хранится в результате; `dataOrFail()` выбросит исключение.
`Wait` вместо `Throw` позволит ждать освобождения квоты.

Локальная квота принадлежит экземпляру клиента. Для нескольких workers подключите
[общий Redis backend](../../reference/integrations/redis.md).
Квота операции дополняет общую; retry расходует новые разрешения, cache hit их
не расходует. [Полный контракт rate-limit](../../reference/execution/rate-limit.md).

## Кеш ответов

Передайте PSR-16 store. Здесь используется учебное хранилище в памяти; приложение
подставляет своё хранилище с нужным сроком жизни.

```php
use Brahmic\ApiSutra\Config\CacheConfig;
use Example\ClientShowcase\DemoClient;
use Example\ClientShowcase\Support\MemoryStore;

$store = new MemoryStore();
$cached = $base->with(cacheConfig: new CacheConfig(store: $store, ttl: 60))
    ->with(timeout: 7); // Копия сохраняет store и все параметры кеша.
$cachedClient = new DemoClient($cached, $transport);
$first = $cachedClient->records()->get(7)->send()->dataOrFail();
$second = $cachedClient->records()->get(7)->send()->dataOrFail();
```

Два чтения за время TTL выполняют один HTTP-запрос. Кеш хранит ответ API, из которого
каждый раз создаётся DTO: `$first !== $second`. Это отдельный механизм от кеша метаданных.

| Действие | Наблюдение в примере |
| --- | --- |
| Два одинаковых чтения | Всего 1 HTTP-вызов |
| Ещё одно чтение с `withoutCache()` | Всего 2 HTTP-вызова |
| `clearCache()` на клиенте и новое чтение | Всего 3 HTTP-вызова |

ApiSutra разделяет пространства по подключению и известной auth identity;
одинаковые подключения могут разделять кеш. Это кеш по TTL, без автоматической
HTTP revalidation. Допустимые методы, tenant, собственные authenticators и режимы
чтения/записи описаны в [справочнике кеша](../../reference/execution/cache.md).

## DTO на входе и выходе

В [RecordDto](../../example/client-showcase/src/Resources/Records/RecordDto.php) объявлены
`int $id`, `string $title` и `array $_extra = []`. Подключим строгую проверку
скалярных типов и сбор неизвестных полей:

```php
use Brahmic\ApiSutra\Serialization\Rules\DtoRules;
use Brahmic\ApiSutra\Serialization\Rules\HydrationRules;
use Brahmic\ApiSutra\Serialization\Rules\RulePolicy;
use Brahmic\ApiSutra\Serialization\Rules\ScalarPolicy;
use Example\ClientShowcase\Resources\Records\RecordDto;

$rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
    ->withDto(RecordDto::class, DtoRules::create()->extras('_extra'));
$typed = $base->with(hydrationRules: $rules);
```

| Сценарий у клиента с `$typed` | Результат |
| --- | --- |
| Получен `future_flag: true` | `$record->_extra = ['future_flag' => true]` |
| Получен строковый `id: "7"` | `hydration_error`, путь `data.id` |
| `toArray()` у корректного DTO | `id`, `title` и `_extra` под своим именем |
| Тот же DTO передан в `SaveRecordRequest` через `BodyRoot` | JSON содержит только `id` и `title` |

Поле `_extra` объявляется явно, а сбор включает `extras('_extra')`. Имя само по себе
не задаёт поведения. Один набор передаётся гидратору и сериализатору клиента;
он исключает receiver из запросов и для вручную созданного объекта.

Для формата обычных полей запроса доступны `queryArrayFormat`, `serializeNulls`,
политики дат и enum, `wireBodySerializationPolicy`:
[настройки сериализации](../../reference/serialization/README.md).
Атрибуты, mapping, вложенность, variants и scoped handlers показаны в
[обзоре возможностей DTO](../dto/showcase.md).

## Диагностика и ошибки

Подключите PSR-3 logger и включите debug для разбора подготовленного запроса:

```php
use Example\ClientShowcase\DemoClient;
use Example\ClientShowcase\Support\MemoryLogger;
use Psr\Log\LogLevel;

$logger = new MemoryLogger();
$diagnostic = $authenticated->with(logger: $logger, logLevel: LogLevel::INFO, debug: true);
$diagnosticClient = new DemoClient($diagnostic, $transport);
$execution = $diagnosticClient->records()->get(7)->withTraceId('record-7');
$result = $diagnosticClient->send($execution)->raw();
$debug = $result->requestDebug();
```

`traceId` результата и `trace` журнала равны `record-7`. В `requestDebug()` заголовок
`Authorization` заменён на `***`. Безопасный снимок и сырой `debug` различаются;
дополнительные секретные поля задаются через `redaction`.
[Логи, уровни и диагностика](../../reference/results/observability.md).

Для HTTP 404 пример проверяет три способа обработки:

| Способ | Поведение |
| --- | --- |
| `send()->raw()` | Результат с ошибкой `not_found` для собственного ветвления |
| `send()->dataOrFail()` | Исключение вместо отсутствующего DTO |
| Клиент с `throwOnErrors: true` | Исключение уже из `send()` |

[Полный контракт результатов и исключений](../../reference/results/handles.md).

## Копии конфигурации и разовые опции

```php
use Brahmic\ApiSutra\Request\RequestOptions;
use Example\ClientShowcase\DemoClient;

$preview = $authenticated->with(baseUrl: 'https://preview.example.test', timeout: 4, auth: null);
$previewClient = new DemoClient($preview, $transport);
$previewClient->records()->get(7)->send()->dataOrFail();
$request = $client->records()->get(7);
$options = RequestOptions::empty()->withTimeout(2, connectTimeout: 1)->withoutAuth();
$client->send($request->withOptions($options))->dataOrFail();
$request->send()->dataOrFail();
```

Копия `ClientConfig` используется при создании нового клиента. Явный `auth: null`
сбрасывает основную стратегию; именованные `authScopes` сохраняются.
У preview адрес другой, таймаут 4 секунды, Bearer не отправляется.
Разовый вызов исходного клиента получает 2 секунды и выполняется без auth.
Следующий вызов **того же request** снова использует 15 секунд и исходный Bearer.

`with()` не перестраивает уже созданного клиента. Runtime-методы возвращают
execution-копию; их приоритеты зависят от конкретной настройки.
[Все параметры ClientConfig](../../reference/client/configuration.md) ·
[Опции одного запроса](../../reference/request/declaration.md#runtimeопции-частые).

## Следующие возможности

| Потребность | Продолжение |
| --- | --- |
| Создавать клиентов через контейнер или Laravel | [Сборка зависимостей](../../reference/client/construction.md), [Laravel](../integration/laravel.md) |
| Подключить несколько сервисов и версий | [Мегаклиент](../integration/multi-service.md), [версии](../../reference/client/versioning.md) |
| Настроить страницы, ожидание задач и параллельные вызовы | [Пагинация](../recipes/pagination.md), [continuation](../recipes/continuation.md), [batch/pool](../../reference/execution/batch-pool.md) |
| Добавить собственное поведение | [Расширения](../recipes/extensions.md), [маппинг ошибок](../../reference/results/errors.md) |

[Исполняемый пример](../../example/client-showcase/README.md) · [Документация](../../README.md).
