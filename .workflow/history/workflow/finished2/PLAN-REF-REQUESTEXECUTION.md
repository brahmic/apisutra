# План: RequestExecution (опции отдельно, DX без клонов)

## Цель
Убрать клоны для runtime‑настроек, сохранив привычный DX (`request->with*()->send()`), и оставить декларацию/данные запроса неизменяемыми.

## Идея
Ввести обёртку `RequestExecution`, которая держит:
- исходный `AbstractRequest` (данные + декларация),
- `RequestOptions` (runtime‑настройки).

`with*()` возвращают `RequestExecution`, а pipeline использует **исходный запрос** для атрибутов/сериализации и **options** для override‑ов.

## Нюансы бизнес‑логики
- Атрибуты должны читаться по исходному классу запроса, не по обёртке.
- `resolveEndpoint()` / `resolveBaseUrl()` остаются приоритетными.
- Приоритеты override > attribute > config неизменны.
- `CacheManager` и `RequestPreparer` сохраняют refresh‑mode `withoutCache()`.
- В `PipelineContext` нужно хранить `options` отдельно, чтобы все компоненты использовали единый источник override‑ов.

## Шаги
1. **Контракт и обёртка**
   - Создать интерфейс `RequestExecutionInterface` (например, в `Contracts/Core`):
     - `getRequest(): RequestInterface`
     - `getOptions(): RequestOptions`
   - Создать класс `RequestExecution` (например, в `Request/`):
     - implements `RequestExecutionInterface` и `RequestInterface`;
     - `send()/sendAsync()` делегируют в клиент;
     - `getMethod/getEndpoint/getResponseType` делегируют в исходный запрос.
     - Методы `with*` объявлены явно (через trait/интерфейс), без `__call` — для IDE‑подсказок.
   - Зафиксировать контракт: кастомные методы запроса вызываются **до** `with*`.

2. **AbstractRequest**
   - Изменить `with*()` (cache/retry/auth/delay/idempotency/timeout/trace/role/header/baseUrl) так, чтобы они возвращали `RequestExecutionInterface` вместо `static`.
   - Добавить `withOptions(RequestOptions $options): RequestExecutionInterface`.
   - Оставить `withPage/withLimit/withCursor` как модификацию данных запроса (клоны допустимы, это уже не runtime).

3. **PipelineContext**
   - Добавить `?RequestOptions $options` в `PipelineContext` (и в `child()` — по умолчанию `null`).
   - В `PipelineContextFactory` и `Pipeline::execute()`:
     - если входной объект `RequestExecutionInterface`, развернуть его:
       - `$options = $execution->getOptions()`
       - `$request = $execution->getRequest()`
     - контекст создавать с исходным запросом и options.

4. **Использование options в пайплайне**
   Обновить компоненты, чтобы читать override‑ы из `RequestOptions`:
   - `RequestPreparer` (`resolveTraceId`, `applyRequestOverrides`).
   - `CacheManager` (`resolveCacheOverride`).
   - `RetryConfigResolver` (`resolve`).
   - `DelayApplier`, `RateLimitApplier`.
   - `AuthHandler` (`getAuthDisabledOverride`).
   *Fallback:* если `options` отсутствуют — использовать текущие методы запроса (для совместимости).

5. **Paginator**
   - Обновить так, чтобы при работе с `RequestExecution` не терялись options.
   - `withPage/withLimit/withCursor` на `RequestExecution` должны возвращать новую обёртку с теми же options.

6. **Batch/Pool**
   - В `BatchExecutor` и `PoolExecutor` учитывать `RequestExecutionInterface`:
     - при `setClient()`/`withRole()` применять их к исходному запросу;
     - возвращать `RequestExecution` с теми же options.

7. **Документация**
   - Добавить `RequestExecution` в `docs/architecture/02-core-classes.md` и `docs/glossary.md`.
   - Примеры DX оставить прежними.
   - Примечание о порядке вызовов (кастомные методы до `with*`) — добавить после рефакторинга (как отдельный шаг).

## Критерии приёмки
- `with*()` больше не клонируют запрос для runtime‑настроек.
- Pipeline использует исходный request для атрибутов/сериализации и options для override‑ов.
- Бизнес‑логика неизменна (кеш, retry, auth, idempotency).
