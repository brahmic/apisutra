# P0-08: CompositeRequestInterface::aggregate() — сигнатура

## Проблема
- `../docs/architecture/01-contracts.md` описывает `aggregate(ResultCollection)`,
  а `../docs/features/request-pipeline.md` и `../docs/glossary.md` —
  `aggregate(ResultCollection, PipelineContext)`.

## Канонический источник
- `../docs/architecture/01-contracts.md`

## Рекомендуемое решение (с аргументацией)
- Утвердить сигнатуру с `PipelineContext`:
  `aggregate(ResultCollection $results, PipelineContext $ctx): mixed`.
  Аргументы: агрегатору часто нужен доступ к конфигурации, traceId и роли запроса;
  явный `PipelineContext` избегает скрытых зависимостей.
- Обновить контракт в `../docs/architecture/01-contracts.md`
  и примеры в `../docs/architecture/03-pipeline.md` при необходимости.
