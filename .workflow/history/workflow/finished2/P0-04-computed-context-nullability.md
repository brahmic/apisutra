# P0-04: computed() — nullable PipelineContext

## Проблема
- В примерах встречался non‑null контекст, хотя контракт допускает `null`.

## Канонический источник
- `../docs/architecture/01-contracts.md`

## Рекомендуемое решение (с аргументацией)
- Зафиксировать `computed(array $data, ?PipelineContext $ctx = null): array`
  везде (контракты, примеры, глоссарий).
  Аргументы: `Dto::from()` работает без контекста, метод должен быть безопасен.
