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
- `unwrap` — путь к финальным данным внутри JSON-объекта poll/start ответа и критерий их присутствия
- `pollRequest` — optional poll-request; если не указан, берётся `ClientConfig::defaultPollRequest`
- `stateResolver` — класс `ContinuationStateResolverInterface`, создаваемый без аргументов
- `defaultMode` — режим запроса; runtime override имеет приоритет, далее используется режим клиента

Готовность задаётся явно. В порядке приоритета выбирается `stateResolver` атрибута,
встроенный `FinalPathStateResolver` при непустом `unwrap`, затем
`ClientConfig::continuationStateResolver`. Если ничего не задано, ожидание завершается
`ContinuationConfigurationException` до первого poll-запроса.

Встроенный resolver читает `$result->response?->json()`. Значение по `unwrap`,
отличное от null, означает Ready; отсутствие пути, null, отсутствие ответа или
тело, которое не является JSON-объектом, означают Pending. Значения `false`, `0`
и `[]` присутствуют и считаются Ready. Корень ответа не подставляется вместо пути.
Гидратация выполняется только после Ready: возможность создать DTO с defaults
не является признаком готовности.

`finalType` также автоматически попадает в `$client->responseDtoCatalog()` как
запись с `kind = ResponseDtoKind::AsyncFinal`, рядом со start-DTO из `Returns(...)`
(`kind = Sync`). См. [Operation Inventory → ResponseDtoCatalog](./operation-inventory.md#responsedtocatalog).

### 2) Режим provider-выполнения

Используйте `ContinuationMode`:
- `Auto` — оценить старт: Ready возвращает финал, Pending начинает polling по token
- `Sync` — оценить старт: Ready возвращает финал, Pending даёт `final_not_ready`
- `Async` — не оценивать готовность старта, сразу начать polling по token

Failed прекращает ожидание в любом оцениваемом ответе, даже при наличии token.

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
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Enums\Continuation\ContinuationMode;
use Brahmic\ApiSutra\Serialization\VO\RequestPartsBag;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

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

## Собственный критерий готовности

Для протокола со статусом операции реализуйте resolver в SDK. Он получает один
`ContinuationContext` на всё ожидание: `finalType` (null для нетипизированного
финала), `unwrap`, `sourceRequestClass` и `mode`. Resolver не выполняет HTTP
и не гидратирует DTO. Его исключения выходят без обёртки.

```php
use Brahmic\ApiSutra\Continuation\ContinuationContext;
use Brahmic\ApiSutra\Continuation\ContinuationState;
use Brahmic\ApiSutra\Contracts\Interfaces\Continuation\ContinuationStateResolverInterface;
use Brahmic\ApiSutra\Result\ExecutionResult;

final readonly class OperationStateResolver implements ContinuationStateResolverInterface
{
    public function resolve(ExecutionResult $result, ContinuationContext $context): ContinuationState
    {
        $data = $result->response?->json();
        return match (is_array($data) ? ($data['status'] ?? null) : null) {
            'done' => ContinuationState::ready($data['data'] ?? null, 'data'),
            'failed' => ContinuationState::failed(),
            default => ContinuationState::pending(),
        };
    }
}
```

Укажите `stateResolver: OperationStateResolver::class` в `ContinuationResult`
или `continuationStateResolver: new OperationStateResolver()` в конфигурации клиента.
`ClientConfig::with()` переносит экземпляр; явный null снимает настройку.
Без `ContinuationResult` вызов `await()` с resolver клиента возвращает Ready-payload
без преобразования, в том числе null. `awaitAs()` задаёт тип явно.

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

`awaitByToken()` берёт критерий из декларации указанного класса; `awaitByTokenAs()`
требует resolver клиента и передаёт контекст без `unwrap` и `sourceRequestClass`.
Оба входа используют Async. Само получение token не требует resolver.

## Лимит, кеш и диагностика

`ContinuationAwaitOptions(maxAttempts: 30, intervalMs: 1000)` ограничивает число
poll-запросов. Стартовый ответ этот лимит не расходует; после последнего poll
паузы нет. Поле ошибки `attempts` считает оценённые ответы, включая старт в Auto/Sync:
при `maxAttempts: 1` и двух Pending в Auto оно равно 2, в Async — 1.

Повторный `await()` или `awaitAs()` того же типа возвращает кешированный результат.
Другой тип в `awaitAs()` гидратируется из сохранённого Ready-payload без HTTP;
ошибка преобразования сохраняет прежний кеш. Все входы используют гидратор клиента.
Для собственного `ClientInterface` конструктор сервиса требует явный гидратор:
`new ContinuationService($client, $hydrator)`; допустим `Hydrator::default()`.

`ContinuationService::resolveFromStartResult()` возвращает `ContinuationOutcome`
с `value`, исходным `payload`, его `path`, `lastResult` и `attempts`.
`hydrateOutcome($outcome, $type)` преобразует тот же payload в другой тип.
Методы `awaitFromStartResult()`, `awaitByToken()` и `awaitByTokenAs()` возвращают значение.

Ошибки ожидания описаны в [руководстве ошибок](./errors.md#ошибки-ожидания-continuation).
HTTP-ответ доступен через `ContinuationAwaitException::lastResult` независимо от debug.
Автоматический лог включает безопасный `context()`, без payload и token.

## Инварианты и ошибки конфигурации

- poll-request должен иметь **ровно один обязательный scalar-параметр** конструктора (token)
- если `pollRequest` не задан в атрибуте, должен быть `defaultPollRequest` в `ClientConfig`
- если token extractor не настроен, `await()`/`awaitByToken()` не смогут продолжить async-сценарий
- failed poll/start ответ продолжает polling только если resolver вернул Pending и есть token
- ошибки continuation-конфига бросаются как `ContinuationConfigurationException`

## Миграция с эвристического ожидания

Объявите `unwrap` или resolver. Для корневого финала без обёртки нужен resolver,
который явно возвращает Ready. При отсутствии/null `unwrap` путь больше не заменяется
корневым payload. Ошибка преобразования Ready теперь немедленно даёт
`ContinuationAwaitException(final_hydration_failed)` с исходной `HydrationException`
в previous. Для лимита, отсутствия token и неготового Sync обрабатывайте runtime-ошибку
ожидания; `ContinuationConfigurationException` относится к неверной настройке.
Это изменение совместимости alpha-версии; прежнего режима эвристики нет.
