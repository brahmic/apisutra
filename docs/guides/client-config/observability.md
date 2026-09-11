# Observability (debug/environment/logging)

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

Политика также применяется к штатным логам и recorder; настройка дополнительных
полей и границы безопасного экспорта описаны в [логировании](../logging.md).

## Environment
`environment` влияет на поведение ядра:
- `Local/Testing` — отключает кеш метаданных атрибутов
- `Production/Staging` — включает кеш метаданных

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
