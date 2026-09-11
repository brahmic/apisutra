# Execution (обзор)

Короткий обзор того, как выполняются запросы и как выбирается режим исполнения.

## Режимы выполнения
- **Sync**: обычный синхронный вызов (`send()`).
- **Async**: асинхронный (`sendAsync()`), результат через Promise/ResultHandle.

## RequestExecution
`RequestExecution` — runtime‑обёртка запроса с опциями (`RequestOptions`) и пагинацией (`PaginationOptions`).
Создаётся при вызове `with*()` и управляет отправкой.

## Пагинация
Если задан `PaginationRule` (клиент или override), запрос может выполняться через `Paginator`.
Режим выбирается по правилам (single/all/pages/range).

## Batch / Pool
Для массового выполнения используются `batch()` и `pool()` у клиента.
Batch — фиксированная коллекция запросов, Pool — контролируемая конкурентность.

## Где подробности
- Жизненный цикл запроса: `docs/technical/pipeline.md`
- Пагинация: `docs/glossary/pagination.md`
- Результаты: `docs/glossary/results.md`
