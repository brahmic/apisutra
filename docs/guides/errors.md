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

## Локальный rate-limit и HTTP 429

Локальная квота даёт `rate_limited` с `reason=local_rate_limit_exceeded` и `retryAfter`
в секундах. Если HTTP ещё не было, response равен null. Сбой store или атомарного backend даёт `execution_error`
с `reason=rate_limit_backend_error`. Обе причины останавливают HTTP retry. Реальный
ответ 429 сохраняет прежнюю HTTP-обработку. Подробный [контракт и примеры](client-config/rate-limit.md#ожидание-и-ошибки).

`RequestException::$response` имеет тип `?ProviderResponse`, поэтому обработчики
всей этой иерархии должны проверять наличие ответа. `catch (RateLimitException)`
и retryAfter сохранены. При локальном отказе после предыдущей HTTP-попытки её ответ
доступен в результате и в exception.lastResponse; exception.response остаётся null.
[Изменения совместимости](client-config/rate-limit.md#миграция-с-прежнего-замещения).

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

Подтверждённые Guzzle/cURL ошибки отправки/чтения 55/56 и пустого ответа 52
классифицируются как `connection_failed`, код 28 — как `timeout`.
Сохраняется original exception в previous; текст ошибки не используется для
угадывания сетевого кода. PSR-18 путь не требует Guzzle Client.

### Состояние отправки

`TransportException::transmissionState` — enum `TransmissionState` из
`Enums\Http`, относящийся к попытке. По умолчанию значение `Unknown` (`unknown`):
исход отправки неизвестен. `NotSent` (`not_sent`) допустим только при доказательстве,
что запрос не был отправлен. Собственный транспорт отвечает за это утверждение.
Timeout, пустой ответ и обрыв чтения/записи не доказывают отсутствие отправки.
Generic PSR network failure и один DNS errno также не дают такой гарантии.

Для транспортного сбоя или локального deadline смотрите
`$result->errors->first()?->context['transmissionState']`:
здесь состояние накоплено по всем попыткам текущего запроса. Неопределённая ранняя
попытка не исчезает после более позднего not_sent. Deadline до первой отправки
даёт not_sent; после возможной отправки — unknown. Auth и дочерние запросы имеют
свои состояния, это не статус всей бизнес-операции приложения. В исключении
ExecutionDeadlineException pipeline сохраняет накопленное состояние текущего запроса.

Диагностика не разрешает автоматический POST retry и не доказывает, выполнил ли
сервер операцию. После потери ответа приложение может использовать предусмотренную
провайдером проверку результата или ключ идемпотентности.

### Декодирование ответа

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
сохраняется в Auto; явный [RawResponse](attributes/response.md#rawresponse)
обходит обработчики формата и JSON. Подробнее — [файловые ответы](files.md).

### Ошибки структуры и диапазона чисел

Нарушения контрактов полей DTO, строгий `Returns::unwrap`, JsonCast и защита int возвращают `hydration_error` с
`HydrationException`. Для этих ошибок `RequestError::context` и свойства исключения
содержат `reason`, `path`, `expected`, `actual`:

| reason | Смысл |
| --- | --- |
| `unwrap_path_missing` | Указанный путь отсутствует; actual — `missing`. |
| `unexpected_response_shape` | По пути найден null/scalar вместо данных объявленного DTO. |
| `integer_out_of_range` | Число не помещается в int; actual — тип исходного значения. |
| `required_field_missing` | Обязательное поле отсутствует. |
| `null_not_allowed` | Итоговое значение null не допускается объявленным типом. |
| `invalid_field_type` | Значение после casts не подходит типу поля или параметра. |
| `invalid_json` | JsonCast не может разобрать вложенную JSON-строку. |
| `invalid_datetime` | Дата не принята выбранной политикой Throw; expected включает формат. |
| `unknown_nested_variant` | Nested в режиме Error не нашёл вариант; expected содержит объявленные допустимые варианты. |

Например, `path=data.item.id`, `expected=int`, `actual=string` указывает поле с
переполнением. Для вложенных DTO используются имена свойств, для коллекций —
порядковый индекс (`items[1].id`), без включения внешних ключей и значений в диагностику.
Это сведения о новых проверках; у остальных HydrationException эти свойства могут быть null.

Исходный HTTP-ответ остаётся в результате. Ошибка гидратации не запускает новый HTTP
retry. `raw()`/`resolved()` сообщают ошибку, `dataOrFail()` и `throwOnErrors` выбрасывают
исключение; sync и promise API используют один контракт.
Миграция unwrap описана в [Returns](attributes/response.md#строгий-unwrap),
числовые типы — в [сериализации](serialization.md#большие-целые-в-ответах).

### Подробная диагностика гидратации

Автоматический ERROR log для перечисленных встроенных ошибок содержит
`reason`, `path`, `expected`, `actual`, `httpStatus`, класс запроса (`request`) и
`traceId`. Actual сообщает тип или missing/null, а сообщение не включает исходное
значение. Например, `items[2].price`, expected `float`, actual `array` указывает
нарушенный контракт. Для `Nested` режимы Skip/KeepRaw сохраняют прежнее поведение.

Если для расследования нужно само значение, прочитайте исходный ответ из результата:

```php
// $request — настроенный запрос SDK-провайдера с объявленным DTO ответа.
$result = $request->send()->raw();
$error = $result->errors->first();
$diagnostic = $error?->context;           // Путь, причина и ожидаемый тип.
$body = $result->response?->body;         // Исходное тело HTTP-ответа без маскирования.
```

`response` и его body доступны при `debug: false`: ошибка гидратации не маскирует
и не пересобирает ответ. SDK не требует нового режима конфигурации или хранилища
диагностических данных. Провайдер может явно сохранить нужный ответ при обработке
результата по своим правилам доступа и маскирования. Raw body и `previous` не являются
очищенным экспортом; не отправляйте их целиком в общий автоматический лог.
Гарантия безопасных сообщений относится к перечисленным встроенным проверкам,
а не к произвольному тексту пользовательских casts/конструкторов.

Миграция: ошибки missing/null/типов, дат и Nested Error теперь дают
`HydrationException` / `hydration_error` вместо прежних ошибок PHP или
`configuration_error`. Повреждённый JSON в JsonCast больше не превращается в null.
[Правила обязательности и defaults](dto.md#обязательные-поля-и-ошибки-гидратации)
работают автоматически, без новых обязательных настроек.

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


### Недоступная валидация

Если объявлены `#[Validate]`, но совместимая фабрика недоступна или provider не смог
её предоставить, запрос завершается с `configuration_error` до HTTP. Список
`validationErrors` пуст: данные не были проверены. Неверные данные при работающей
фабрике по-прежнему дают `validation_failed` с ошибками полей.
Прямые `validate()`/`isValid()`/`errors()` при недоступном движке выбрасывают
`ConfigurationException`. [Контракт и миграция](validation.md#миграция-и-различие-ошибок).


При отказе обновления авторизации после основного 401 сохраняются оба ответа:
основной — в `response`, refresh — в `AuthRefreshFailedException::dependencyResult`.
Автоматический context содержит `reason=auth_refresh_failed`, без credentials и
полного результата зависимости. [Контракт восстановления](auth.md#восстановление-после-401-и-миграция).


Batch/pool сохраняют классификацию и фактический ответ HTTP, decoding, hydration,
configuration и hook ошибок независимо от throwOnErrors. `connection_failed` означает
подтверждённую сетевую ошибку; неизвестное исключение даёт `execution_error`.
Существующее различие стратегий по выбрасыванию исключений сохраняется.
Ошибка [recording](testing.md#ошибки-recording-и-миграция) может сопровождаться HTTP 200:
основная операция уже выполнена, повторять её как сетевой сбой нельзя.
