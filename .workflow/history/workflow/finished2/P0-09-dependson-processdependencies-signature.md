# P0-09: DependsOnRequestInterface::processDependencies() — сигнатура

## Проблема
- Контракты и feature‑доки используют разные сигнатуры
  (с `PipelineContext` и без).

## Канонический источник
- `../docs/architecture/01-contracts.md`

## Рекомендуемое решение (с аргументацией)
- Утвердить сигнатуру с `PipelineContext`:
  `processDependencies(ResultCollection $results, PipelineContext $ctx): void`.
  Аргументы: обработке зависимостей нужен доступ к контексту запроса и конфигу,
  без этого появляются скрытые зависимости.
- Обновить контракт в `../docs/architecture/01-contracts.md`
  и синхронизировать `../docs/glossary.md`.
