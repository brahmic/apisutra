# Логи, trace и debug

Этот раздел про настройки клиента, влияющие на наблюдаемость.

## Logger и logLevel
`logger` — PSR‑3 логгер.
`logLevel` — минимальный уровень логов (по умолчанию INFO).

Если `logger` не задан, логирование не выполняется.

```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Psr\Log\LogLevel;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    logger: $logger,
    logLevel: LogLevel::WARNING,
);
```

## Debug
`debug = true` включает:
- `ExecutionResult::debug` (PreparedRequest/ProviderResponse/тайминги)
- payload в `PipelineEvent` (audit‑лог)

Дополнительно в `ExecutionResult` доступны helper-методы:
- `requestDebug()` — массив с:
  - `method`, `url`, `headers`, `bodyRaw`, `hasStream`
  - `body`, `query`, `form` (если доступны в prepared meta)
  - `oneOf` (если есть контрактная диагностика)
  - `credentialsEnrichment` (тех.метка применённого enrichment)
- `requestDebugJson()` — JSON этого снимка

Если debug-данные не собраны, методы возвращают `null`.

Если запрос использует `RequestOneOf`/`RequestDiscriminator`, в `requestDebug()['oneOf']`
появляется краткая диагностика:
- `contract`
- `matchedVariant`
- `discriminator` (`field`, `value`, `variant`)

Если включён provider credentials enrichment, `requestDebug()['credentialsEnrichment']`
содержит:
- `applied` — применялось ли enrichment
- `scope` — выбранный scope (если был)
- `mergeMode` — итоговый merge-режим
- `fields` — список затронутых ключей по `body/query/form`

Это удобно для анализа, но может содержать большие тела ответов.

## Redaction в requestDebug
По умолчанию `requestDebug()` маскирует секреты:
- userinfo и секретные query-параметры в URL
- чувствительные headers (`Authorization`, `Cookie`, `X-Api-Key` и др.)
- ключи в `body/query/form` по списку:
  - built‑in ключи (`password`, `token`, `secret`, `api_key`, ...)
  - `credentialsConfig.secretKeys`

Если нужна «сырая» диагностика, можно отключить маскирование:
```php
$raw = $result->requestDebug(false);
$rawJson = $result->requestDebugJson(false);
```

`ClientConfig::redaction` передавать не требуется: встроенная `RedactionPolicy`
создаётся автоматически. Явная политика нужна только для дополнительных секретных
заголовков, полей и путей конкретного провайдера.

Политика также применяется к штатным логам и recorder; настройка дополнительных
полей и границы безопасного экспорта описаны в [логировании](observability.md).

## Environment
`environment` влияет на поведение ядра:
- `Local/Testing` — отключает кеш метаданных атрибутов
- `Production/Staging` — включает кеш метаданных

Кеш хранит описание деклараций, а не общие объекты из constructor defaults или
аргументов атрибутов. Выбор environment не меняет их изоляцию между DTO и операциями
одного клиента. См. [defaults DTO](../dto/lifecycle.md#значения-по-умолчанию-и-изоляция-объектов)
и [объектные аргументы атрибутов](../dto/lifecycle.md#объектные-аргументы-атрибутов).

Также `environment` используется в auto‑discovery для включения кеша
в режиме `DiscoveryCacheMode::Auto`.

```php
use Brahmic\ApiSutra\Enums\Configuration\Environment;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    environment: Environment::Testing,
    debug: true,
);
```

ApiSutra даёт два уровня наблюдаемости:
- **Audit‑лог** — структурированный журнал этапов pipeline
- **Логи** через PSR‑3 — сообщения о ключевых событиях

## TraceId
TraceId связывает все этапы выполнения запроса и используется:
- в audit‑событиях
- в логах (как `trace`)
- в `ExecutionResult::traceId`

### Приоритет источников traceId
1) `withTraceId()` на конкретном запросе
2) `withTraceId()` в runtime‑опциях (через RequestExecution)
3) traceId, заданный на клиенте (`$client->setTraceId()`)
4) сгенерированный UUID

## Audit‑лог
Audit хранится в `ExecutionResult::$audit` как массив `PipelineEvent`:
- stage (`PipelineStage`)
- timestamp + duration
- requestClass
- role (`RequestRole`)
- payload (только при debug)

## Debug‑payload
При `ClientConfig::debug = true` в audit‑событиях и в `ExecutionResult::debug`
появляется `DebugInfo`:
- `preparedRequest` (метод/URL/заголовки/тело)
- `response` (status/headers/body)
- `duration`
- `nested` (для batch/pool)

**Нюанс:** debug‑payload может содержать большие тела ответов.

## Логи (PSR‑3)
Если в `ClientConfig` задан `logger`, SDK пишет события:
- завершение запроса
- ошибки валидации
- исключения
- повторные запросы (retry)

Минимальный уровень настраивается через `logLevel` (PSR‑3).

## Маскирование безопасного экспорта

**Параметр `ClientConfig::redaction` необязателен:** по умолчанию уже используется
`new RedactionPolicy()`. Создавать и передавать этот объект для включения базовой
защиты не нужно. Достаточно обычного `new ClientConfig(baseUrl: ...)`.

Общая `RedactionPolicy` применяется к `requestDebug()`/`requestDebugJson()`,
структурированному context штатного PSR-3 logger и записываемым фикстурам.
Она маскирует стандартные credential headers, Cookie/Set-Cookie, известные
password/token/secret-поля, URL userinfo и query credentials, включая повторяющиеся
и percent-encoded имена. `credentialsConfig.secretKeys` дополняет правила для
подготовленного запроса. Исходные HTTP-данные и позиция stream не изменяются.

Явно передавайте политику, когда у провайдера есть **дополнительные** секретные
заголовки, поля или вложенные пути, которых нет во встроенных правилах. Если
стандартных правил и `credentialsConfig.secretKeys` достаточно, параметр опустите.
Например, для специфичных credentials провайдера:

```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Diagnostics\RedactionPolicy;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    redaction: new RedactionPolicy(
        headers: ['X-Provider-Credential'],
        fields: ['provider_secret'],
        paths: ['accounts.*.credential'],
    ),
);
```

`fields` действуют на любой глубине, `paths` задают пути внутри JSON-тела/данных;
`*` соответствует одному уровню. Правила добавляются к встроенным и не отключают
их. `ClientConfig::with()` сохраняет политику, а `$client->record()` передаёт её
recorder. При самостоятельной сборке `RecordingTransport` базовая политика также
работает автоматически; аргумент `redaction` нужен только для собственных правил.

Невалидный JSON в безопасном экспорте заменяется маркером `[redacted-body]`;
form-urlencoded маскируется по именам полей. Произвольный текст ошибок,
неструктурированный текст и нестандартные форматы не гарантированно очищены:
правила не ищут любой возможный секрет в любом месте строки.

Прямые `ExecutionResult::debug`, `response` и audit payload остаются raw-объектами.
Их произвольная сериализация не является безопасным экспортом. Для осознанного
raw-снимка запроса доступны `requestDebug(false)` и `requestDebugJson(false)`.

**Изменение совместимости:** секрет в URL теперь маскируется вместе с query;
recorder применяет базовую защиту даже без пользовательского Fixture. Fixture
добавляет свои правила и replacement values; старые уже записанные файлы
автоматически не переписываются.

## Размер safe debug/log

По умолчанию тело в safe debug/log ограничено 64 KiB (65536 байт). При превышении
тело пропускается целиком: `bodyOmitted=true`, `bodyOmissionReason=body_size_limit`,
`bodySize` содержит исходный размер. JSON не обрезается. Исходный HTTP-ответ и replay
fixture сохраняют свой размер; потоки не читаются и не перематываются для диагностики.
`ProviderResponse::duration`, `DebugInfo::duration` и `PipelineEvent::duration` измеряются
в миллисекундах; timestamp остаётся Unix-временем в секундах.

RedactionPolicy по-прежнему необязательна. Её передают для дополнительных секретных
полей либо когда нужно увеличить лимит, сохранив маскирование:

```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Diagnostics\RedactionPolicy;

$config = new ClientConfig(
    baseUrl: 'https://api.example.test',
    redaction: new RedactionPolicy(maxBodyBytes: 262144),
);
```

Миграция: код, читавший большие тела из `requestDebug()`, должен учитывать маркер
пропуска или увеличить предел. Явный `requestDebug(false)` остаётся raw-доступом
без маскирования и ограничения размера; для обычного разбора увеличьте safe-предел.
