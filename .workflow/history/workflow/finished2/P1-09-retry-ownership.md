# P1-09: Retry — где реализован

## Проблема
- В документации упоминается retry как часть pipeline и как часть transport,
  но нет единого описания источника истины.

## Рекомендуемое решение (с аргументацией)
- Зафиксировать retry на уровне pipeline (RetryHandler), а transport оставить
  одноразовым исполнителем запроса.
  Аргументы: retry использует `shouldRetry()` и `RetryConfig`, что логично
  принадлежит pipeline‑уровню.
- Обновить описание в `../docs/architecture/03-pipeline.md` и
  `../docs/features/retry-rate-limiting.md`.
