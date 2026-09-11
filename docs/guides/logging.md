# Логирование, Audit и TraceId

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

## Где ещё смотреть
- `docs/guides/client-config/observability.md` — debug и environment
- `docs/technical/pipeline.md` — обзор пайплайна

## Маскирование безопасного экспорта

Общая `RedactionPolicy` применяется к `requestDebug()`/`requestDebugJson()`,
структурированному context штатного PSR-3 logger и записываемым фикстурам.
Она маскирует стандартные credential headers, Cookie/Set-Cookie, известные
password/token/secret-поля, URL userinfo и query credentials, включая повторяющиеся
и percent-encoded имена. `credentialsConfig.secretKeys` дополняет правила для
подготовленного запроса. Исходные HTTP-данные и позиция stream не изменяются.

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
recorder. При самостоятельной сборке `RecordingTransport` передайте её аргументом
`redaction`.

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
