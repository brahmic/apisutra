# Ошибки и результаты

SDK не бросает исключения по умолчанию. Вместо этого возвращается `ExecutionResult`,
который содержит статус, ошибки и метаданные.

## ExecutionResult
Ключевые поля:
- `status`: SUCCESS / PARTIAL / FAILED
- `data`: данные (может быть null)
- `errors`: ошибки выполнения/транспорта
- `validationErrors`: ошибки валидации
- `exception`: исходное исключение (если есть)
- `meta`, `audit`, `debug`, `traceId`

Для быстрого просмотра подготовленного HTTP-запроса:
- `requestDebug()` -> `?array` (`method`, `url`, `headers`, `bodyRaw`, `hasStream`, `oneOf`)
- `requestDebugJson()` -> `?string` (JSON снимка)

По умолчанию `requestDebug*` маскирует чувствительные заголовки
(`Authorization`, `Cookie`, `X-Api-Key` и т.д.).

## ResultHandle
```php
$handle = $request->send();

$raw = $handle->raw();          // ExecutionResult
$resolved = $handle->resolved();// ResolvedResultInterface
$data = $handle->dataOrFail();  // бросит исключение при FAILED
$token = $handle->continuationToken(); // ?string
$tokenStrict = $handle->continuationTokenOrFail(); // string или SdkException
$final = $handle->await();      // unified async-await (если настроен continuation-контракт)

$request = $raw->requestDebug();     // ?array
$requestJson = $raw->requestDebugJson(); // ?string

// Sugar через ResultHandle:
$request2 = $handle->requestDebug();      // ?array
$requestJson2 = $handle->requestDebugJson(); // ?string
```

## ResolvedResult: удобное чтение ошибок
```php
$resolved = $request->send()->resolved();

$error = $resolved->error();          // ?ClientError (первая ошибка)
$code = $resolved->errorCode();       // ?string
$message = $resolved->errorMessage(); // ?string
$status = $resolved->errorStatus();   // ?int
$providerTraceId = $resolved->errorProviderTraceId(); // ?string
$token2 = $resolved->continuationToken(); // ?string

// Полный список ошибок (batch/pool/composite)
$views = $resolved->errorViews();     // array<ClientError>

// Типизированный контекст (если задана фабрика)
$context = $resolved->errorContext();  // ?object
$contexts = $resolved->errorContexts();// array<object|null>
```

`errorCode()` возвращает первый доступный код по приоритету:
`appCode → clientCode → providerCode → sdkCode`.

`ClientError` находится в `Brahmic\ApiSutra\VO\Errors`.
Raw‑контекст всегда доступен через `$resolved->error()?->context`.
`errorProviderTraceId()` вернёт `null`, если typed‑контекст отсутствует
или не содержит `providerTraceId`.

**`providerTraceId` в контексте:** добавляйте это поле **только** при подтверждённой поддержке
трассировки со стороны провайдера (документация, реальные ответы). Без подтверждения — не вводите;
подробнее: [Методология провайдера](./provider-methodology.md#providertraceid--errorprovidertraceid).

Примечание: `errorRetryable()` и `errorCategory()` по умолчанию возвращают `null`
— их нужно определять на уровне SDK провайдера.

`continuationToken()` и `continuationTokenOrFail()` работают через
`ClientConfig::continuationTokenExtractor`. Если extractor не задан,
`continuationToken()` вернёт `null`.

### Типизированный контекст ошибок
Чтобы получить typed‑контекст, задайте `errorContextFactory` в `ClientConfig`.
Если фабрика не задана — `errorContext()` вернёт `null`, а `errorContexts()` — пустой массив.

### Системные ключи context
Ядро стандартизирует набор ключей:
- `traceId`
- `httpStatus`
- `requestClass`
- `providerCode` (после маппинга в `ClientError`)

Ключи доступны как `SystemErrorContextKeys` в `Brahmic\ApiSutra\VO\Errors`.

Порядок merge:
1) системный context ядра  
2) `RequestError.context`  
3) контекст из `ClientErrorMapper` (last‑write‑wins)

### RequestContractViolation
Если нарушен class-level контракт запроса (`RequestOneOf`/`RequestDiscriminator`),
SDK возвращает ошибку `ErrorCode::RequestContractViolation` до HTTP-вызова.

Ключи в `RequestError.context`:
- `contract` — имя oneOf-контракта;
- `discriminatorField` — поле discriminator;
- `discriminatorValue` — текущее значение discriminator;
- `matchedVariant` — фактически выбранный/разрешённый вариант;
- `filledVariants` — список фактически заполненных вариантов;
- `violations` — машинно-читаемые причины нарушения контракта.

Типовые `violations.code`:
- `none_selected`, `multiple_selected`
- `required_common_missing`, `unknown_variant_field`
- `unknown_discriminator_field`, `unknown_discriminator_value`
- `discriminator_mismatch`, `prohibited_variant_fields`
- `invalid_dot_path_root` (когда dot-path указывает в scalar-корень)

Для успешных запросов с oneOf в `requestDebug()` дополнительно доступен блок `oneOf`
с краткой диагностикой выбранного варианта.

## throwOnErrors
Если `ClientConfig::throwOnErrors = true`:
- sync‑вызовы бросают исключение
- async‑вызовы reject‑ят promise

Если `false`, исключения упаковываются в `ExecutionResult`.

## Политика ошибок
Можно переопределять поведение в `AbstractRequest` или `AbstractClient`:
- `hasRequestFailed()`
- `shouldRetry()`
- `getRequestException()`

Порядок разрешения: **request → client → default**.

Дефолтная логика:
- `hasRequestFailed()` → HTTP статус `>= 400`
- `shouldRetry()` → `false`
- `getRequestException()` → `null`

Переопределяйте, если у провайдера есть нестандартный сигнал ошибки
(например, `status` в body) или нужен свой критерий retry/исключений.

## ClientResponse и маппинг ошибок
Для внешних API удобнее преобразовать результат:
- `ResolvedResultFactoryInterface` → `ResolvedResultInterface`
- `ClientResponseFactoryInterface` → `ClientResponse`
- `ClientErrorMapperInterface` → `ClientError`
- `ClientErrorFactory` → единый маппинг для `ClientResponse` и DX‑методов

## Control‑flow исключения
- `EarlyReturnException` — завершить пайплайн успехом без HTTP
- `RetryableException` — форсировать retry на уровне обработки

## Где детали
- Ошибки и статусы: `docs/glossary/results.md`
- Обработка ошибок: `docs/technical/error-handling.md`
- Continuation token: `docs/guides/continuation-token.md`
- Provider async-await: `docs/guides/provider-async-await.md`
