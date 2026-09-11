# Provider Async Await

Единый DX для провайдеров, где один и тот же бизнес‑метод может работать
в sync/async режиме и возвращать continuation token.

## Что решает этот подход

- один request-класс на бизнес-действие, без дублирования `...SyncRequest`/`...AsyncRequest`
- режим выполнения провайдера задаётся как опция (`Sync|Async|Auto`)
- финальный DTO для async-сценария описывается декларативно
- polling и await остаются в ядре, а provider-протокол — в provider-пакете

## Базовые элементы

### 1) Контракт финального результата

Используйте class-level атрибут `#[ContinuationResult]`:

```php
use Brahmic\ApiSutra\Attributes\Response\ContinuationResult;
use Brahmic\ApiSutra\Attributes\Response\Returns;

#[Returns(StartEnvelopeDto::class)]
#[ContinuationResult(
    finalType: FinalBusinessDto::class,
    unwrap: 'data',
    pollRequest: GetAsyncResultRequest::class,
)]
final class CheckRequest extends BaseRequest
{
    // ...
}
```

- `finalType` — обязательный финальный DTO для `await()`
- `unwrap` — optional путь к финальным данным внутри poll/start payload
- `pollRequest` — optional poll-request; если не указан, берётся `ClientConfig::defaultPollRequest`

`finalType` также автоматически попадает в `$client->responseDtoCatalog()` как
запись с `kind = ResponseDtoKind::AsyncFinal`, рядом со start-DTO из `Returns(...)`
(`kind = Sync`). См. [Operation Inventory → ResponseDtoCatalog](./operation-inventory.md#responsedtocatalog).

### 2) Режим provider-выполнения

Используйте `ContinuationMode`:
- `Auto` — сначала попытка immediate final, потом fallback в polling по token
- `Sync` — ожидаем финал сразу, без fallback
- `Async` — сразу сценарий через token/polling

Runtime sugar на запросе:
- `asProviderSync()`
- `asProviderAsync()`
- `asProviderAuto()`
- `withContinuationMode(...)`

### 3) Маппинг режима в provider-протокол

Ядро не хардкодит `async`/`mode`/`header` поля провайдера.
Это делает `ContinuationModeApplicatorInterface`:

```php
use Brahmic\ApiSutra\Contracts\Interfaces\Continuation\ContinuationModeApplicatorInterface;
use Brahmic\ApiSutra\Enums\Continuation\ContinuationMode;

final class ProviderContinuationModeApplicator implements ContinuationModeApplicatorInterface
{
    public function apply(RequestInterface $request, RequestPartsBag $parts, ContinuationMode $mode, ?PipelineContext $context = null): RequestPartsBag
    {
        $parts->query['async'] = [
            'value' => $mode === ContinuationMode::Async,
            'format' => null,
        ];

        return $parts;
    }
}
```

## Конфигурация клиента

```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Continuation\ContinuationMode;

$config = new ClientConfig(
    baseUrl: 'https://api.provider.test',
    continuationTokenExtractor: new ProviderContinuationTokenExtractor(),
    continuationModeApplicator: new ProviderContinuationModeApplicator(),
    defaultContinuationMode: ContinuationMode::Auto,
    defaultPollRequest: GetAsyncResultRequest::class,
);
```

## DX в прикладном коде

### Обычный вызов (auto)

```php
$final = $client->checks()->check(new CheckRequest(...))->await();
```

### Принудительный sync

```php
$final = $client->checks()->check(
    (new CheckRequest(...))->asProviderSync(),
)->await();
```

### Принудительный async

```php
$handle = $client->checks()->check(
    (new CheckRequest(...))->asProviderAsync(),
);

$final = $handle->await();
```

### Token-only сценарий (без стартового запроса)

```php
$final = $client->continuation()->awaitByToken(
    token: $token,
    sourceRequestClass: CheckRequest::class,
);
```

Или типизировать явно:

```php
$final = $client->continuation()->awaitByTokenAs(
    token: $token,
    finalType: FinalBusinessDto::class,
);
```

## Инварианты и ошибки конфигурации

- poll-request должен иметь **ровно один обязательный scalar-параметр** конструктора (token)
- если `pollRequest` не задан в атрибуте, должен быть `defaultPollRequest` в `ClientConfig`
- если token extractor не настроен, `await()`/`awaitByToken()` не смогут продолжить async-сценарий
- если poll/start ответ помечен как failed, но содержит continuation token, polling продолжается
- ошибки continuation-конфига бросаются как `ContinuationConfigurationException`

