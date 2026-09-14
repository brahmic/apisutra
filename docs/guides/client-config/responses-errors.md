# Responses & Errors

Настройки обработки результатов и ошибок.

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
продолжают вызываться. Подробнее: [hooks](../hooks.md#beforehydrate-изменение-данных)
и [классификация ошибок](../errors.md#классификация-ошибок-json-и-исполнения).

Для намеренного чтения тела без декодирования используйте необязательные
[`#[RawResponse]` или `withRawResponse()`](../attributes/response.md#rawresponse).
`ResultHandle::raw()` сам по себе возвращает ExecutionResult и не отключает JSON.

## throwOnErrors
```php
use Brahmic\ApiSutra\Config\ClientConfig;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    throwOnErrors: true,
);
```

Если `throwOnErrors = true`, ошибки выбрасываются как исключения
и в async‑режиме попадают в reject.

По умолчанию `throwOnErrors = false` — ошибки остаются в `ExecutionResult`.

## ResolvedResultFactory

```php
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\Result\ContinuationTokenExtractorInterface;
use Brahmic\ApiSutra\Result\ResolvedResult;
use Brahmic\ApiSutra\Result\ResolvedResultFactoryInterface;
use Brahmic\ApiSutra\Result\ResolvedResultInterface;
use Brahmic\ApiSutra\VO\Errors\ClientErrorFactory;
use Brahmic\ApiSutra\VO\Errors\DefaultClientErrorMapper;
use Brahmic\ApiSutra\VO\Errors\ErrorContextFactoryInterface;

final class ExampleResolvedResultFactory implements ResolvedResultFactoryInterface
{
    public function make(ExecutionResult $result): ResolvedResultInterface
    {
        return new ResolvedResult(
            $result,
            new ClientErrorFactory(new DefaultClientErrorMapper()),
        );
    }
}

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    resolvedResultFactory: new ExampleResolvedResultFactory(),
);
```

Если не задавать `resolvedResultFactory`, используется дефолтная реализация,
которая оборачивает `ExecutionResult` и даёт DX‑методы для чтения ошибок.

## ErrorContextFactory
```php
use Brahmic\ApiSutra\VO\Errors\ClientError;

final readonly class ExampleErrorContext
{
    public function __construct(
        public ?string $traceId,
        public ?string $target,
    ) {}
}

final class ExampleErrorContextFactory implements ErrorContextFactoryInterface
{
    public function make(ClientError $error): ?object
    {
        return new ExampleErrorContext(
            traceId: $error->context['traceId'] ?? null,
            target: $error->context['target'] ?? null,
        );
    }
}

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    errorContextFactory: new ExampleErrorContextFactory(),
);
```

Если `errorContextFactory` не задана — `errorContext()` вернёт `null`,
`errorContexts()` — пустой массив. Raw‑контекст доступен через `ClientError::context`.

Системные ключи контекста: `traceId`, `httpStatus`, `requestClass`, `providerCode`.
Порядок merge: system → `RequestError.context` → контекст из `ClientErrorMapper`.

**Поле `providerTraceId`**: добавляйте в typed context **только** если провайдер подтверждённо
возвращает trace-id в ответах на ошибки. См. [Методология провайдера](../provider-methodology.md).

## ContinuationTokenExtractor
```php
final class ExampleContinuationTokenExtractor implements ContinuationTokenExtractorInterface
{
    public function extract(ExecutionResult $result): ?string
    {
        $data = $result->data;
        if (!is_array($data)) {
            return null;
        }

        $token = $data['operationToken'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }
}

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    continuationTokenExtractor: new ExampleContinuationTokenExtractor(),
);
```

Extractor задаёт единый способ извлечения continuation token для `resolved()`:
- `$request->send()->resolved()->continuationToken()`
- `$request->send()->resolved()->continuationTokenOrFail()`
- `$request->send()->continuationToken()` и `continuationTokenOrFail()` через `ResultHandle`

Если extractor не задан, токен считается отсутствующим (`null`).

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

## Provider Async Await defaults

```php
use Brahmic\ApiSutra\Enums\Continuation\ContinuationMode;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    continuationTokenExtractor: new ExampleContinuationTokenExtractor(),
    continuationModeApplicator: new ProviderContinuationModeApplicator(),
    defaultContinuationMode: ContinuationMode::Auto,
    defaultPollRequest: GetAsyncResultRequest::class,
);
```

- `defaultContinuationMode` — дефолт режима provider-выполнения для запросов без runtime override.
- `defaultPollRequest` — poll-request по умолчанию для `awaitByToken()`/`awaitByTokenAs()`.
- `continuationModeApplicator` — провайдерный маппинг `ContinuationMode` в реальный протокол (`query/body/header`).
- `continuationStateResolver` — экземпляр `ContinuationStateResolverInterface` для явной
  готовности Pending/Ready/Failed. Применяется после `ContinuationResult::stateResolver`
  и встроенного resolver по непустому `unwrap`; для `awaitByTokenAs()` обязателен.

Подробный DX и контракты: [Provider Async Await](../provider-async-await.md).

Пример defaults выше предполагает критерий в `ContinuationResult` запроса:
`stateResolver` или непустой `unwrap`. Для `awaitByTokenAs()`, а также ожидания
без такого объявления добавьте клиентский `continuationStateResolver`.

## ClientResponseFactory / ErrorMapper

```php
use Brahmic\ApiSutra\Response\ClientResponse;
use Brahmic\ApiSutra\Response\ClientResponseFactoryInterface;
use Brahmic\ApiSutra\VO\Errors\ClientError;
use Brahmic\ApiSutra\VO\Errors\ClientErrorMapperInterface;
use Brahmic\ApiSutra\VO\Errors\RequestError;

final class ExampleClientResponseFactory implements ClientResponseFactoryInterface
{
    public function make(ResolvedResultInterface $result): ClientResponse
    {
        return new ClientResponse(status: 200, headers: [], body: 'ok');
    }
}

final class ExampleClientErrorMapper implements ClientErrorMapperInterface
{
    public function map(RequestError $error): ClientError
    {
        return new ClientError(
            providerCode: $error->response?->status,
            sdkCode: $error->code,
            message: $error->message,
        );
    }
}

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    responseFactory: new ExampleClientResponseFactory(),
    errorMapper: new ExampleClientErrorMapper(),
);
```

Если `responseFactory` не задан, используется дефолтный `ClientResponseFactory`.
`errorMapper` опционален и нужен только для кастомного формата ошибок.

`ClientResponseFactory` превращает `ResolvedResult` в удобный для приложения ответ.
`ClientErrorMapper` контролирует формат ошибок и итоговый HTTP‑статус.

## ResolvedResult: DX‑методы ошибок
```php
$resolved = $request->send()->resolved();

$error = $resolved->error();          // ?ClientError
$code = $resolved->errorCode();       // ?string
$message = $resolved->errorMessage(); // ?string
$status = $resolved->errorStatus();   // ?int
$views = $resolved->errorViews();     // array<ClientError>
$context = $resolved->errorContext(); // ?object
```

Дефолтная фабрика:
- SUCCESS → 200 + data
- PARTIAL → 207 + data + errors
- FAILED → статус из `ClientErrorMapper`
