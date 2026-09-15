# Ожидание результата операции

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

Подробный DX и контракты: [Provider Async Await](../../guides/recipes/continuation.md).

Пример defaults выше предполагает критерий в `ContinuationResult` запроса:
`stateResolver` или непустой `unwrap`. Для `awaitByTokenAs()`, а также ожидания
без такого объявления добавьте клиентский `continuationStateResolver`.

Унифицированный DX для long-running сценариев: токен продолжения читается
на уровне `resolved()` результата, а не через provider-specific helper в ресурсах.

## Что это такое

`continuation token` — идентификатор операции, который провайдер возвращает
в стартовом ответе (например, `operationToken`, `taskId`, `jobId`), чтобы потом
получить статус или итог операции.

В `apisutra` токен извлекается pluggable-стратегией:
- `ContinuationTokenExtractorInterface`
- `ClientConfig::continuationTokenExtractor`
- DX-методы `ResolvedResultInterface`:
  - `continuationToken(): ?string`
  - `continuationTokenOrFail(): string`

Без extractor поведение безопасное и предсказуемое: `continuationToken()` вернёт `null`.

Этот гайд покрывает только получение token.
Полный async-await DX (mode/applicator/polling) описан отдельно:
[Provider Async Await](../../guides/recipes/continuation.md).

## Базовый пример

```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Result\ContinuationTokenExtractorInterface;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Override;

final class ProviderContinuationTokenExtractor implements ContinuationTokenExtractorInterface
{
    #[Override]
    public function extract(ExecutionResult $result): ?string
    {
        $payload = $result->response?->json();
        if (!is_array($payload)) {
            return null;
        }

        $token = $payload['operationToken'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }
}

$config = new ClientConfig(
    baseUrl: 'https://api.provider.test',
    continuationTokenExtractor: new ProviderContinuationTokenExtractor(),
);
```

## Использование в прикладном коде

```php
$handle = $client->operations()->start($request);

$token = $handle->continuationToken(); // ?string
$required = $handle->continuationTokenOrFail(); // string, иначе SdkException

// Эквивалентно через resolved():
$resolvedToken = $handle->resolved()->continuationToken();
```

Если нужно не только получить token, но и дождаться финала операции —
используйте:
- `$handle->await()` / `$handle->awaitAs(...)`
- `$client->continuation()->awaitByToken(...)`

Наличие token само по себе не определяет состояние операции. Ожидание дополнительно
требует `ContinuationResult::unwrap` либо resolver готовности. Ready гидратируется,
Pending продолжает polling, Failed завершает его даже при token. Подробности и
миграция — в [контракте ожидания](../../guides/recipes/continuation.md).
Чтение HTTP JSON в примере работает и при `Returns`, когда `$result->data` уже DTO.

## Рекомендации для провайдерного SDK

- Не хардкодьте ключи токена в ядре `apisutra`.
- Делайте extractor на стороне провайдерного пакета, где известен payload-контракт.
- Возвращайте `null`, если токен отсутствует или невалиден.
- `continuationTokenOrFail()` используйте в сценариях, где токен обязателен по контракту.

## Ошибки ожидания continuation

`ContinuationAwaitException` находится в `Brahmic\ApiSutra\Exceptions\Continuation`
и наследует `SdkException`. Его `reason` различает:

| reason | Причина |
| --- | --- |
| `final_hydration_failed` | Ready-payload не преобразуется в финальный DTO, включая неверную форму |
| `final_not_ready` | Sync получил Pending |
| `continuation_token_missing` | Pending без token у результата без ошибки |
| `attempts_exhausted` | После последнего разрешённого poll результат остаётся Pending |
| `continuation_failed` | Resolver вернул Failed для результата без ошибки |

`attempts` — число оценённых ответов, `lastResult` — последний `ExecutionResult`
с HTTP-ответом. При `final_hydration_failed` previous содержит исходную
`HydrationException`, путь которой включает `unwrap`/path resolver. Неверная форма
Ready-payload даёт `unexpected_response_shape` с путём payload или `$`.
Это правило действует также при смене типа в кешированном `awaitAs()`.

`context()` и автоматический лог содержат reason, attempts, httpStatus, traceId
и вложенный hydration-контекст. Payload, token и значения полей туда не включаются;
исходный ответ доступен явно через `lastResult` при любом значении debug.
Failed и Pending без token доставляют исходное исключение failed-результата через
`throw()`; исключения resolver и конфигурации DTO не оборачиваются.
Неверная настройка ожидания остаётся `ContinuationConfigurationException`.

Критерии готовности, примеры и [миграция](continuation-await.md#миграция-с-эвристического-ожидания)
описаны в руководстве ожидания. Стартовый `send()`/`raw()` сохраняет поведение
result-first и `throwOnErrors`; ошибка await не заменяет его результат.

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

Ошибки ожидания описаны в [руководстве ошибок](continuation-await.md#ошибки-ожидания-continuation).
HTTP-ответ доступен через `ContinuationAwaitException::lastResult` независимо от debug.
Автоматический лог включает безопасный `context()`, без payload и token.

## Миграция с эвристического ожидания

Объявите `unwrap` или resolver. Для корневого финала без обёртки нужен resolver,
который явно возвращает Ready. При отсутствии/null `unwrap` путь больше не заменяется
корневым payload. Ошибка преобразования Ready теперь немедленно даёт
`ContinuationAwaitException(final_hydration_failed)` с исходной `HydrationException`
в previous. Для лимита, отсутствия token и неготового Sync обрабатывайте runtime-ошибку
ожидания; `ContinuationConfigurationException` относится к неверной настройке.
Это изменение совместимости alpha-версии; прежнего режима эвристики нет.

## Внешние правила финального DTO

Гидратор клиента применяет `hydrationRules` к Ready-payload и повторному `awaitAs()`.
Strict-ошибка оборачивается в `final_hydration_failed` сразу, даже при наличии token.
Путь финала дополняет `sourcePath`; при Ready без объявленного пути происхождение
Unavailable. [Диагностика внешних правил](../dto/diagnostics.md#диагностика-и-входы).
