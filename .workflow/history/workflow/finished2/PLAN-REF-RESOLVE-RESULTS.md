# План: ResultHandle + ResolvedResult (вариант B)

## Цель
Сделать единый DX‑подход для получения **чистых данных** из запросов через один тип результата (ResultHandle), с контролируемой пагинацией и явным управлением async.

## Зафиксированные решения
1) `send()` и `sendAsync()` возвращают **ResultHandle** (breaking change).
   - `send()` запускает sync‑выполнение (по умолчанию).
   - `send(mode: SendMode::Async)` запускает async‑выполнение.
   - `sendAsync()` — тонкий алиас `send(mode: SendMode::Async)`.
2) `ResultHandle` — единый тип результата:
   - `resolved()` → `ResolvedResultInterface<T>` (по умолчанию `ResolvedResult`)
   - `dataOrFail()` → `T` (или exception)
   - `resolvedAsync()` → `PromiseInterface<ResolvedResultInterface<T>>`
   - `raw()` → `ExecutionResult`
3) На запросе добавляем удобные методы‑обёртки:
   - `resolved()` = `send()->resolved()`
   - `dataOrFail()` = `send()->dataOrFail()`
   - `resolvedAsync()` = `sendAsync()->resolvedAsync()`
4) Пагинация:
   - по умолчанию **одна страница**, без скрытого `all()`;
   - параметры берутся из `#[Pagination]`/`ClientConfig::paginationConfig`;
   - agгрегация страниц — только через явные `rules()`;
   - default‑правило задаётся в `ClientConfig` (fallback).
5) Асинхронность:
   - поведение не меняется атрибутами;
   - явное управление через `send(mode: SendMode::Async)`/`sendAsync()`/`resolvedAsync()`.
6) Клиентский ответ:
   - в core есть default `ClientResponse`, чтобы работать из коробки;
   - `ClientResponseFactoryInterface` создаёт ответ из результата;
   - клиент может подменить фабрику для `MClientResponse`.

## Архитектурные элементы
1) `ResultHandle`:
   - хранит `ExecutionResult` или `PromiseInterface<ExecutionResult>`;
   - отвечает за преобразование в `ResolvedResult<T>`.
2) `ResolvedResult<T>` (Result Object):
   - `data(): T`
   - `isSuccess()/isPartial()/isFailed()/hasData()/hasErrors()/errors()`
   - `result(): ExecutionResult` (доступ к debug/meta/ошибкам).
   - default implementation of `ResolvedResultInterface`.
3) `ResolvedResultInterface<T>`:
   - единый контракт для возвращаемого результата;
   - позволяет клиенту вернуть `MClientResult`.
4) `PaginationRule` (VO):
   - `single()` (default)
   - `pages(int $count)`
   - `range(int $from, int $to)`
   - `all()`
   - `failStrategy(FailStrategy $strategy)`
5) `RequestResolver` (сервис):
   - принимает `RequestInterface` + `PaginationRule`;
   - решает: один HTTP‑вызов или `Paginator->run(...)`;
   - возвращает `ExecutionResult`/`PaginatedResult` для `ResultHandle`.
6) `ClientResponse` (VO):
   - `status`, `headers`, `body`;
   - default‑реализация для out‑of‑the‑box.
7) `ClientResponseFactoryInterface`:
   - `make(ResolvedResultInterface $result): ClientResponse`;
   - default factory → `ClientResponse`;
   - клиент может подменить factory.

## Шаги реализации
### Этап 1 — ResultHandle / ResolvedResult
1) Добавить `ResultHandle` в `src/Result/ResultHandle.php`.
2) Добавить `ResolvedResultInterface` в `src/Result/ResolvedResultInterface.php`.
3) Добавить `ResolvedResult` в `src/Result/ResolvedResult.php` (default impl).
4) Добавить `ResolvedResultFactoryInterface`:
   - default factory → `ResolvedResult`;
   - клиент может подменять фабрику для `MClientResult`.
5) Добавить `PaginationRule` в `src/Pagination/PaginationRule.php`.
6) Добавить default‑правило пагинации в `ClientConfig` (`paginationRule`).
7) Расширить `RequestOptions` и `RequestOptionsChainTrait`:
   - хранение `paginationRule`;
   - метод `rules(PaginationRule $rule)`.
8) Ввести `RequestResolver`:
   - общий путь для sync/async;
   - логика пагинации через `Paginator`.
9) Обновить `ClientInterface`/`AbstractClient`:
   - `send()`/`sendAsync()` → `ResultHandle`;
   - добавить `SendMode` enum и параметр `send(mode: SendMode $mode = SendMode::Sync)`.
10) Обновить `AbstractRequest`/`RequestExecution`:
   - `send(mode: SendMode $mode = SendMode::Sync)`/`sendAsync()` → `ResultHandle`;
   - добавить `resolved()`/`dataOrFail()`/`resolvedAsync()` (обёртки).
11) Тесты этапа 1:
   - `send()`/`sendAsync()` возвращают `ResultHandle`;
   - `resolved()`/`dataOrFail()`/`resolvedAsync()` для обычного запроса;
   - пагинация: default single page; `rules(all/pages/range)`;
   - `dataOrFail()` кидает при ошибке.
12) Документация этапа 1:
   - обновить `example/request-use-cases.md` (ResultHandle/ResolvedResult);
   - добавить термины в glossary (ResultHandle, ResolvedResultInterface, ResolvedResult, resolved, PaginationRule).

### Этап 2 — ClientResponse и маппинг ошибок
1) Добавить `ClientResponse` и `ClientResponseFactoryInterface`:
   - default factory в core;
   - возможность подмены в клиенте.
2) Добавить `ClientResponse` DX‑кейсы и адаптеры (Laravel слой).
3) Определить модель error‑mapping (provider → sdk → client → app).
4) Тесты этапа 2:
   - client response default/custom;
   - error‑mapping сценарии.
5) Документация этапа 2:
   - добавить термины в glossary (ClientResponse, ClientResponseFactory);
   - описать error‑mapping.

## Финальные ожидания
**Поведение**
- `send()`/`sendAsync()` всегда возвращают `ResultHandle`.
- `resolved()` возвращает `ResolvedResultInterface<T>` и не скрывает стоимость пагинации.
- Пагинация «всё сразу» доступна только через явные правила.
- Async управляется только `send(mode: SendMode::Async)`/`sendAsync()`/`resolvedAsync()` (без скрытых атрибутов).
- Клиентский ответ доступен out‑of‑the‑box, но может быть переопределён.

**DX‑кейсы**
```php
// 1) Обычный запрос
$resolved = $request->resolved();
if ($resolved->isSuccess()) {
    $user = $resolved->data(); // UserDto
}

// 2) Жёсткое получение
$user = $request->dataOrFail();

// 3) Пагинация — одна страница (default)
$resolved = $request->resolved();

// 4) Пагинация — все страницы
$resolved = $request
    ->rules(PaginationRule::all())
    ->resolved();

// 5) Async
$resolved = $request->resolvedAsync()->wait();
```
