# ResultHandle и представления результата

`send()` возвращает `ResultHandle`. Его `raw()` даёт `ExecutionResult`, `resolved()` —
прикладное представление результата. `dataOrFail()` извлекает данные или выбрасывает
ошибку при неуспехе. [Учебный SDK](../../example/sdk/run.php) выполняет оба сценария.

## Выбрать уровень результата

| API | Результат и назначение |
| --- | --- |
| `request->send()` / `client->send($request)` | ResultHandle для дальнейшего чтения |
| `request->resolved()` / `handle->resolved()` | ResolvedResultInterface: данные, статус и ошибки |
| `request->dataOrFail()` / `handle->dataOrFail()` | Данные либо исключение |
| `handle->raw()` | ExecutionResult, включая meta/audit/debug; не строка HTTP |
| `request->sendAsync()` | ResultHandle в режиме SendMode::Async |
| `handle->rawAsync()` / `resolvedAsync()` | Promise соответствующего результата |
| `client->response($resolved)` | ClientResponse для передачи ответа приложению |

Promise не гарантирует неблокирующий I/O; [фактическая семантика](../execution/transport.md).
`await()` и token-only ожидание относятся к [операции провайдера](../execution/continuation-await.md).
Для намеренного чтения тела без декодирования нужен RawResponse, а не raw().

## Успешный ответ без DTO

Для обычного запроса без DTO, пагинации, `#[Download]` и обработчика расширения
`ExecutionResult::data`, `resolved()->data()` и `dataOrFail()` возвращают:

| Ответ | Значение |
| --- | --- |
| HTTP 204 или тело длиной 0 байт | `null` |
| JSON `null` | `null` |
| JSON `false`, `0`, строка | Соответствующее значение без замены на массив |
| JSON-массив или объект | PHP-массив; `{}` и `[]` дают `[]` |
| Непустой `text/plain` | Исходная строка, включая пробелы и переводы строк |
| Другой явно указанный не-JSON Content-Type | Исходная строка, даже если тело похоже на JSON |
| Непустой ответ без `Content-Type` | Строго разбирается как JSON |

JSON определяется по `application/json` и суффиксу `+json`; регистр MIME и параметры
вроде `charset` не влияют на выбор. Некорректный JSON, включая тело только из пробелов,
даёт `response_decoding_error` с исходным HTTP-ответом. HTTP 204 не разбирается даже
при наличии тела. `null` в успешном результате допустим: проверяйте `isSuccess()`,
а не наличие данных; `dataOrFail()` возвращает такой `null` без исключения.

Это изменение прежнего поведения, при котором `null`, пустое тело и текст могли
превращаться в `[]`. Неизвестные явно указанные MIME больше не разбираются как JSON
и не подменяются пустым массивом; для декодирования специального формата можно
зарегистрировать расширение. `#[Download]` возвращает
`FileResponse`; выбранный обработчик расширения получает исходный ответ. Если он
возвращает `null`, применяется стандартный разбор.

При объявленном DTO пустое тело/JSON `null` по-прежнему передаётся в гидрацию как
пустой набор полей; результат зависит от обязательных полей и defaults DTO. Для
пагинации сохраняется контракт массива. Скаляры вместо DTO или данных пагинации
дают `hydration_error`.
Непустой ответ не-JSON формата для DTO/пагинации без обработчика расширения даёт
`response_decoding_error`, reason `unsupported_response_content_type`.

`BeforeHydrate` сохраняет сигнатуру массива и вызывается только для массивов.
Для обычных `null`, скаляров и текста он пропускается; `AfterResponse` и `AfterHydrate`
продолжают вызываться. Подробнее: [hooks](../extensions/hooks.md#beforehydrate-изменение-данных)
и [классификация ошибок](errors.md#классификация-ошибок-json-и-исполнения).

Для намеренного чтения тела без декодирования используйте необязательные
[`#[RawResponse]` или `withRawResponse()`](../attributes/response.md#rawresponse).
`ResultHandle::raw()` сам по себе возвращает ExecutionResult и не отключает JSON.

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
подробнее: [Методология провайдера](../../guides/sdk/first-operation.md#первая-сквозная-операция).

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

## Фабрики представлений

`ClientConfig::resolvedResultFactory` задаёт `ResolvedResultFactoryInterface` с
`make(ExecutionResult): ResolvedResultInterface`. Без override используется
`ResolvedResultFactory`. Свой результат должен сохранять полный интерфейс, включая
статус, ошибки и доступ к исходному ExecutionResult.

`responseFactory` задаёт `ClientResponseFactoryInterface` с
`make(ResolvedResultInterface): ClientResponse`. Без override стандартный ответ
даёт 200 для success, 207 для partial и статус, определённый error mapper, для failure.
Если меняется только представление ошибок, достаточно `errorMapper`; собственная
фабрика всего результата не требуется. [Ошибки и маппинг](errors.md).

Для HTTP-ответа приложения Laravel используется
`Brahmic\ApiSutra\Contracts\Interfaces\Response\ClientResponseAdapterInterface`.
[Контракт адаптера](../integrations/laravel.md#clientresponseadapter).

Частичный результат, например после пагинации или составного исполнения, может
содержать одновременно данные и ошибки: проверяйте `isPartial()` и `errors()`.
Доступ к raw ответу через debug требует `debug: true`; безопасный экспорт описан
в [observability](observability.md).

## Provider ResultMetaExtractor
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

final class ExampleResultMetaExtractor implements ResultMetaExtractorInterface
{
    public function extract(ExecutionResult $result): ?ResultMeta
    {
        $payload = $result->response?->json();
        if (!is_array($payload)) {
            $payload = $result->errors->first()?->response?->json();
        }

        if (!is_array($payload)) {
            return null;
        }

        $code = $payload['resultCode'] ?? null;
        $message = $payload['resultMessage'] ?? null;
        $token = $payload['operationToken'] ?? null;

        if (!is_int($code) || !is_string($message) || !is_string($token)) {
            return null;
        }

        return new ProviderEnvelopeMeta(
            resultCode: $code,
            resultMessage: $message,
            operationToken: $token,
        );
    }
}

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    resultMetaExtractor: new ExampleResultMetaExtractor(),
);
```

Назначение:
- вынести технические envelope-поля (`resultCode/resultMessage/operationToken`) из endpoint DTO;
- централизованно заполнить `ExecutionResult->meta` для обычных запросов.

Инварианты применения:
- если `ExecutionResult->meta` уже заполнена (pagination/batch/composite), extractor не перезаписывает её;
- если extractor вернул `null`, результат остаётся без изменений;
- если `resultMetaExtractor = null`, поведение полностью backward-compatible.
- `ExecutionResult->response` доступен независимо от `debug=true/false`, поэтому extractor
  не должен зависеть от debug-режима.
