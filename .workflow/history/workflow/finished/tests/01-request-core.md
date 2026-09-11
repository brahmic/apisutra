# Request Core — план тестирования

## Scope
- `AbstractRequest`, `AbstractResource`
- `RequestSpec`, `RequestSpecResolver`
- `RequestOptions`, `RequestExecution`, `RequestOptionsChainTrait`
- `RequestFactoryInterface` (Laravel RequestFactory)

## Invariants
- `with*` возвращают `RequestExecution`, исходный запрос не мутируется.
- Приоритеты: override > attribute > config.
- `RequestSpec` кэшируется по классу, с учётом окружения (metadata cache).
- Кастомные методы запроса вызываются **до** `with*`.

## Узкие места и целевые тесты
- Переход `AbstractRequest → RequestExecution`: важно не терять runtime‑опции при цепочках.
- Кэш `RequestSpec` по окружениям: нельзя «утащить» метаданные из Production в Testing.
- Порядок приоритетов override/attribute/config должен быть одинаков для cache/retry/timeout.
- Разделение декларации и runtime: `resolveEndpoint/resolveBaseUrl` имеют приоритет над декларацией.

## Сценарии/fixtures (P0)
- **Override > Attribute > Config** (cache/retry/timeout):
  - Вход: ClientConfig с retry/cache; request с `#[Cache]`, `#[Retry]`; runtime `withCache(300)->withRetry(5)`.
  - Ожидание: runtime overrides побеждают, затем атрибуты, затем config.
  - Fixture: не требуется (unit).
- **RequestSpec cache по окружениям**:
  - Вход: два клиента (`Testing` и `Production`) и один класс запроса.
  - Ожидание: в `Testing` кеш не используется, в `Production` — используется.
  - Fixture: не требуется (unit).
- **resolveEndpoint/resolveBaseUrl**:
  - Вход: запрос с `#[Get('/declared')]` + override методов resolve*.
  - Ожидание: используется runtime‑значение.
  - Fixture: не требуется (unit).

## Unit tests
- `RequestOptions`: `withCache/withoutCache`, `withRetry/withoutRetry`, `withDelay/withoutDelay`,
  `withTimeout/withHeader/withRole` корректно формируют override.
- `RequestExecution`: `getOptions()`/`getPaginationOptions()` возвращают текущие значения.
- `RequestSpecResolver`: 
  - кэш работает в Production;
  - в Local/Testing (metadata cache disabled) — кэша нет.
- `AbstractRequest::setClient()` сбрасывает `spec/specResolver`.

## Integration tests
- `AbstractResource::request()` привязывает клиент к запросу.
- `RequestFactory` принимает `Request|array` и корректно резолвит источник.
- `resolveBaseUrl/resolveEndpoint` имеют приоритет над декларацией.

## Edge cases
- `RequestExecution` без клиента → `ConfigurationException`.
- `RequestSpecResolver::clearCache()` влияет на пересоздание `RequestSpec`.

## Fixtures/Mocks
- Мини‑запросы с атрибутами (`#[Get]`, `#[Cache]`, `#[Retry]`).
- Тестовый клиент с `Environment::Testing` и `Environment::Production`.

## Priority
- P0: `with*`→`RequestExecution`, приоритеты override/attribute/config.
- P1: кэш `RequestSpec` по окружениям.
- P2: RequestFactory edge cases.
