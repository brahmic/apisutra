# Ошибки и результаты

SDK не бросает исключения по умолчанию. Вместо этого возвращается `ExecutionResult`,
который содержит статус, ошибки и метаданные.

Ошибки [URI и query](serialization.md#uri-и-path) завершают выполнение до HTTP
и не запускают retry. Неподдерживаемый вид endpoint даёт `configuration_error`
(`ConfigurationException`); отсутствующий/недопустимый path-параметр, неподдерживаемая
query-структура или неоднозначный элемент Comma — `serialization_error`
(`SerializationException`). Режимы raw/resolved, `dataOrFail()` и `throwOnErrors`
сохраняют общий контракт доставки ошибок ниже.

Конфликт готового URL с query/auth/cache, запрещённый перенос credentials,
поздняя смена назначения и неподдерживающий изоляцию транспорт также дают
`configuration_error` без retry. См. [внешние URL](external-urls.md).

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

## Классификация ошибок JSON и исполнения

| Сбой | SDK code |
| --- | --- |
| Кодирование исходящего JSON | `serialization_error` |
| Разбор непустого JSON-ответа | `response_decoding_error` |
| Приведение данных ответа к DTO | `hydration_error` |
| Пользовательский hook | `hook_error` |
| PSR-18 network failure | `connection_failed` |
| Подтверждённый timeout | `timeout` |
| PSR-18 request failure | `invalid_request` |
| Прочий PSR-18 client failure | `transport_error` |
| Локальное чтение, запись или публикация файла | `file_transfer_error` |
| Конфигурация | `configuration_error` |
| Неизвестное исключение исполнения | `execution_error` |

`SerializationException`, `ResponseDecodingException` и `HydrationException` находятся
в `Exceptions\Serialization`. Ошибки стандартного JSON-кодека сохраняют
`JsonException` в `previous`; HTTP-ответ ошибки разбора остаётся в `ExecutionResult::response`.
Проверка pipeline применяется к непустому `application/json`, MIME с суффиксом
`+json` и ответу без `Content-Type`; успешный статус 204 исключён из разбора.
Значения `null`, пустого тела и текста описаны в
[контракте успешного ответа](client-config/responses-errors.md#успешный-ответ-без-dto).
Публичный `ProviderResponse::jsonStrict()` выполняет строгий разбор по явному вызову;
существующий `json()` сохраняет permissive-поведение для совместимости, в том числе
для пользовательских обработчиков HTTP-ошибок обычных строковых ответов.
Потоковый download требует явного чтения `ProviderResponse.stream`: его `json()`
и `jsonStrict()` дают `configuration_error`. Обработка расширений вне download
сохраняется. Подробнее — [файловые ответы](files.md).

### Ошибки структуры и диапазона чисел

Строгий `Returns::unwrap` и защита int возвращают `hydration_error` с
`HydrationException`. Для этих ошибок `RequestError::context` и свойства исключения
содержат `reason`, `path`, `expected`, `actual`:

| reason | Смысл |
| --- | --- |
| `unwrap_path_missing` | Указанный путь отсутствует; actual — `missing`. |
| `unexpected_response_shape` | По пути найден null/scalar вместо данных объявленного DTO. |
| `integer_out_of_range` | Число не помещается в int; actual — тип исходного значения. |

Например, `path=data.item.id`, `expected=int`, `actual=string` указывает поле с
переполнением. Для вложенных DTO используются имена свойств, для коллекций —
порядковый индекс (`items[1].id`), без включения внешних ключей и значений в диагностику.
Это сведения о новых проверках; у остальных HydrationException эти свойства могут быть null.

Исходный HTTP-ответ остаётся в результате. Ошибка гидратации не запускает новый HTTP
retry. `raw()`/`resolved()` сообщают ошибку, `dataOrFail()` и `throwOnErrors` выбрасывают
исключение; sync и promise API используют один контракт.
Миграция unwrap описана в [Returns](attributes/response.md#строгий-unwrap),
числовые типы — в [сериализации](serialization.md#большие-целые-в-ответах).

### Ошибки файлов и транспорта

`FileTransferException` из `Exceptions\Files` содержит стадию, `bytesWritten` и
`partial`; эти поля доступны и в error context. При ошибке финальной записи
сохраняется HTTP-статус принятого ответа. Локальная ошибка не вызывает HTTP retry,
даже если в `retryExceptions` указан общий `Throwable`. При deadline во время
копирования код остаётся `timeout`, с данными о частичной записи.

Известные транспортные исключения нормализуются до решения retry. Исходное исключение
доступно в `previous`; явно настроенный исходный класс в `retryExceptions` продолжает
учитываться. Timeout не определяется по тексту сообщения: поддерживается собственный
`TimeoutException`, общий бюджет повторов и подтверждённый cURL errno 28 у Guzzle
ConnectException. Наличие Guzzle HTTP Client для остальных транспортов не требуется.

После исчерпания повторов последний HTTP-ответ проходит обычный error mapping.
Например, 503 остаётся `service_unavailable`, включая ответ с HTML или испорченным JSON.
Для 400 используется `bad_request`, для остальных немаппируемых 4xx — `client_error`;
ответ и его raw body сохраняются. Строковый `message` из JSON используется как сообщение,
иначе применяется `HTTP <status>`. Если последняя HTTP-попытка завершилась сетевым сбоем,
ответ предыдущей попытки не подставляется вместо отсутствующего текущего ответа.

Произвольное исключение hook не становится сетевым и не вызывает сетевой retry.
Оно сохраняется в `ExecutionResult::exception` без замены исходного объекта.
Типизированные HTTP/configuration-исключения сохраняют свои коды. `EarlyReturnException`
работает и на стадии AfterResponse, завершая выполнение успехом; `RetryableException`
сохраняет специальную управляющую семантику. Ошибки конфигурации DTO не маскируются
как ошибки данных гидратации.

Эти правила одинаковы для sync, promise API и batch: `throwOnErrors` меняет способ
доставки исключения, а не причину сбоя. Политика идемпотентности и replay потоков
в этой поставке не меняется.

## Исчерпание общего бюджета

`ExecutionDeadlineException` является `TimeoutException`. В `raw()->errors` код —
`timeout`, контекст содержит `reason: execution_deadline_exceeded` и `stage` остановки.
Это окончательная ошибка выполнения, даже если последний HTTP-ответ имел статус 200
или 503. Доступный реальный ответ сохраняется в диагностике; до первого HTTP ответа нет.
`raw()`/`resolved()` показывают ошибку, `dataOrFail()` и `throwOnErrors` выбрасывают её. В исключении
`response` сохраняет доступный ответ, в `getPrevious()` — исходную причину.
Таймаут отдельной попытки допускает безопасный retry, общий deadline — нет.
Полный контракт — [Timeouts & Delay](client-config/timeouts-delay.md).
