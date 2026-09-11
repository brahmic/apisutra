# P0-02: PaginableInterface — единый контракт

## Проблема
- Ранее контракт и фичи описывали разные наборы методов.

## Канонический источник
- `../docs/architecture/01-contracts.md`

## Рекомендуемое решение (с аргументацией)
- Утвердить `setPage/setLimit/setCursor/extractMeta` как единый контракт.
  Аргументы: этот набор описан в `../docs/features/pagination.md` и
  `../docs/glossary.md`, покрывает cursor‑based и кастомную meta‑обработку.
