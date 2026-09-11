# Общие фикстуры и сквозные сценарии — план

## Цель
Заранее определить материалы/фикстуры и сквозные кейсы для качественного тестирования SDK.

## Общие фикстуры
- **Pagination**: page‑based / offset‑based / cursor‑based ответы (meta + data).
- **Retry**: sequence 500 → 200, 429 с Retry‑After, 401 → refresh → 200.
- **Cache**: ответ с headers/body, кейс с `#[Cache(key=...)]`.
- **Files**: маленький pdf, zip, base64‑строка.
- **DTO**: nested DTO, enum‑поля, nullable поля.
- **Provider emulation**: шаблоны ответов IIDX/Kontur (sync/async, статусы, коды).

## Сквозные кейсы (end‑to‑end)
1) `withCache()->withPage()`:
   - request → pipeline → cache key учитывает page/limit.
2) Retry + RateLimit + Delay:
   - 429 → retryAfter → success.
3) Auth refresh:
   - 401 → refresh → повтор запроса.
4) Pagination + Hydration:
   - несколько страниц → DTO‑коллекция.
5) Composite/DependsOn:
   - вложенные запросы → агрегация результата.

## Материалы/подготовка
- In‑memory PSR‑16 cache.
- MockTransport + MockResponse::sequence.
- Заглушки Request/DTO классов для тестов.

## Общие хелперы (унификация)
- `TestClientFactory` — единая сборка клиента с тестовыми зависимостями.
- `MockResponseBuilder/Sequence` — шаблоны для типовых последовательностей ответов.
- `Request/DTO stubs` — компактные заглушки для P0‑сценариев.
- `Assert helpers` — проверки контекста/кеша/статусов.
- `TestTrait` — общий setUp/tearDown.

## Приоритет
- P0: retry/auth/cache/pagination.
- P1: composite/dependsOn.
