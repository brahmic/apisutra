# P1-10: Валидация — Result vs Exception

## Проблема
- В `../docs/architecture/03-pipeline.md` указано, что при ошибках
  возвращается `ExecutionResult`, а в `../docs/architecture/02-core-classes.md`
  упоминается `ValidationException`.

## Рекомендуемое решение (с аргументацией)
- Зафиксировать: валидация в pipeline возвращает `ExecutionResult` с
  `ValidationError`, HTTP‑запрос не выполняется; исключения — только при
  `throwOnErrors` или явном `result->throw()`.
  Аргументы: единая стратегия ошибок и отсутствие неожиданного throw.
