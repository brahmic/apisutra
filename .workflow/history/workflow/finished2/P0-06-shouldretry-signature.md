# P0-06: shouldRetry() — единая сигнатура

## Проблема
- В разных документах `shouldRetry()` описан с разной сигнатурой
  (с `attempt` и без него).

## Канонический источник
- `../docs/architecture/02-core-classes.md`

## Рекомендуемое решение (с аргументацией)
- Утвердить `shouldRetry(ProviderResponse $response, int $attempt): bool`.
  Аргументы: `attempt` нужен для backoff/ограничений, параметр явно задаёт
  контекст повтора и упрощает тестирование.
- Обновить `../docs/features/exceptions.md` и
  `../docs/features/retry-rate-limiting.md` под эту сигнатуру.
