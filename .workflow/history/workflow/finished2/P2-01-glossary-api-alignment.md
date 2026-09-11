# P2-01: Глоссарий — AbstractClient/AbstractRequest

## Проблема
- `../docs/glossary.md` перечисляет методы `AbstractClient` и `AbstractRequest`,
  которые не отражены в `../docs/architecture/02-core-classes.md`.

## Рекомендуемое решение (с аргументацией)
- Привести глоссарий в соответствие с `02-core-classes.md`, либо добавить
  недостающие методы в описание core‑классов.
  Аргументы: глоссарий должен отражать реальное публичное API.

## Вопросы
- Эти методы реально существуют в публичном API (`setTraceId`, `clearCache`,
  `isRoot`, `isNested`, `isDependency`, `getRole`, `getContext`)?
