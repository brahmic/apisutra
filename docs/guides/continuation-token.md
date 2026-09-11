# Continuation Token

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
[Provider Async Await](./provider-async-await.md).

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
        $payload = $result->data;
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

## Рекомендации для провайдерного SDK

- Не хардкодьте ключи токена в ядре `apisutra`.
- Делайте extractor на стороне провайдерного пакета, где известен payload-контракт.
- Возвращайте `null`, если токен отсутствует или невалиден.
- `continuationTokenOrFail()` используйте в сценариях, где токен обязателен по контракту.
