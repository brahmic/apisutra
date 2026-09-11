# P0-07: PipelineContext — структура и мутабельность

## Проблема
- В `../docs/features/request-pipeline.md` показан `readonly class PipelineContext`,
  а в `../docs/architecture/03-pipeline.md` и `../docs/glossary.md` указаны
  мутабельные поля (`preparedRequest`, `response`, `dto`).

## Канонический источник
- `../docs/architecture/03-pipeline.md`

## Рекомендуемое решение (с аргументацией)
- Сделать `PipelineContext` мутабельным (без `readonly`).
  Аргументы: pipeline по шагам модифицирует `preparedRequest/response/dto`;
  это уже отражено в архитектурной схеме.
- Синхронизировать структуру полей между
  `../docs/features/request-pipeline.md`, `../docs/glossary.md`
  и `../docs/architecture/03-pipeline.md`.
