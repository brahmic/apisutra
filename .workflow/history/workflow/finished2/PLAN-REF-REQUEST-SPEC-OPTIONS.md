# План: разделение декларации и runtime‑настроек запроса

## Цель
Развести декларацию запроса (атрибуты/метаданные) и runtime‑настройки (cache/retry/timeout/headers/trace/role), сохранив простой API для разработчика и текущую бизнес‑логику.

## Идея
Ввести два уровня:
1) **RequestSpec** — декларативная часть (метод, endpoint, responseType, флаги, pagination‑rules и т.д.), кэшируется по классу запроса.  
2) **RequestOptions** — runtime‑настройки (override‑ы), иммутабельный VO, модифицируется только через `with*`.

`AbstractRequest` остаётся фасадом: внешне API не усложняется, внутри использует `RequestSpec + RequestOptions`.

## Предпосылки
Сейчас атрибуты и runtime‑override‑ы смешаны в одном объекте (`AbstractRequest`). Это упрощает DX, но мешает чистой архитектуре.

## Нюансы бизнес‑логики, которые нельзя нарушить
- `resolveEndpoint()` и `resolveBaseUrl()` всегда имеют приоритет над декларацией из атрибутов.
- `CacheManager` использует `getCacheAttribute()->key` при построении ключа кеша — это поведение должно сохраниться.
- `RequestPreparer` опирается на `getIdempotentAttribute()` и `getIdempotencyKey()` для заголовка идемпотентности — порядок и правила генерации не менять.
- `AuthHandler` полагается на `hasNoAuth()` и `getAuthDisabledOverride()` — их логика должна остаться прежней.
- `CompositeFlow` читает `getExecutionAttribute()` для режима/стратегии выполнения.
- `ResponseHydrator` использует `getResponseType()`, `getReturnsAttribute()`, `getPaginationAttribute()` и `hasDownload()` — значения должны совпадать с текущими.
- `Cache/Retry/RateLimit` приоритеты: override > attribute > config (включая `withoutCache()` как refresh‑mode).
- Семантика override‑ов не меняется: `withoutCache()`/`withoutRetry()` не сбрасывают TTL/attempts, `withoutRateLimit()` сбрасывает rate‑limit и включает флаг disable.
- `RequestFactory` (Laravel) читает атрибуты напрямую — не ломать и не переименовывать атрибуты.

## Основные шаги
1. **RequestSpec**
   - Создать `RequestSpec` (VO) с полями: `method`, `endpoint`, `responseType`, `hasNoAuth`, `hasDownload`, `pagination`, `retry`, `cache`, `timeout`, `execution`, `rateLimit` и т.д.
   - Создать `RequestSpecResolver` (или расширить `RequestAttributeResolver`), который строит `RequestSpec` из атрибутов.
   - Кэшировать `RequestSpec` по классу запроса (предпочтительно через `AttributeMetadataCache` или отдельный кэш).

2. **RequestOptions**
   - Ввести VO `RequestOptions` (можно переименовать `RequestOverrides`).
   - Перенести туда runtime‑поля: `baseUrl`, `cacheEnabled`, `cacheTtl`, `retryEnabled`, `retryAttempts`, `authDisabled`, `delay`, `idempotencyKey`, `rateLimit`, `timeouts`, `traceId`, `role`, `headers`.
   - Реализовать `with*` методы внутри `RequestOptions`.

3. **AbstractRequest как фасад**
   - Упростить `AbstractRequest`: оставить только `RequestOptions` и доступ к `RequestSpec`.
   - `with*()` в `AbstractRequest` делегируют в `RequestOptions`.
   - `getMethod/getEndpoint/getResponseType/get*Attribute()` берут данные из `RequestSpec`, без рефлексии на каждом вызове.
   - Сохранить `resolveEndpoint/resolveBaseUrl` как динамический слой запроса (если задан).

4. **Pipeline / Serializer / Hydrator**
   - Проверить, что приоритеты не меняются: override > атрибут > config.
   - Оставить существующие точки расширения (hooks/attributes).
   - Обновить доступ к override‑ам через `RequestOptions`, без изменения бизнес‑логики.

5. **Документация**
   - Обновить архитектурные разделы: определить `RequestSpec` и `RequestOptions`.
   - Обновить глоссарий.

## Критерии приёмки
- Внешний DX сохраняется: `new Request()->withX()->send()` работает как раньше.
- Атрибуты читаются один раз и кэшируются.
- Runtime‑override‑ы не смешаны с декларацией.
- Бизнес‑логика и приоритеты не изменены.

## Риски
- Несовпадение приоритетов override/attribute/config.
- Скрытая зависимость от рефлексии в сторонних местах.

## Принятые допущения
- Кэш метаданных можно строить поверх существующего `AttributeMetadataCache`.
- Переименование `RequestOverrides` в `RequestOptions` допустимо (чистота терминов важнее).
