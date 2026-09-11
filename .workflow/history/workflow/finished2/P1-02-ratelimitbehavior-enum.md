# P1-02: RateLimitBehavior — единые значения

## Проблема
- В `../docs/glossary.md` указано `Wait` и `Fail`,
  а в `../docs/architecture/06-config.md` — `Wait` и `Throw`.

## Канонический источник
- `../docs/architecture/06-config.md`

## Рекомендуемое решение (с аргументацией)
- Утвердить `Wait | Throw`.
  Аргументы: это определение enum в конфигурации, оно используется в примерах.
- Обновить глоссарий под `Throw`.
