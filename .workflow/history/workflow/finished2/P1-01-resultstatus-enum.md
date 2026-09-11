# P1-01: ResultStatus — единые значения

## Проблема
- `../docs/features/validation.md` использует `ResultStatus::Failure`,
  тогда как остальные документы описывают `SUCCESS | PARTIAL | FAILED`.

## Канонический источник
- `../docs/glossary.md`

## Рекомендуемое решение (с аргументацией)
- Утвердить значения `SUCCESS | PARTIAL | FAILED`.
  Аргументы: большинство документов и примеров уже используют этот набор,
  он соответствует общему стилю enum‑значений.
- Обновить `../docs/features/validation.md` под `FAILED`.
