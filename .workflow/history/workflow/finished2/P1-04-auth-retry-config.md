# P1-04: authRetryOn401/authRetryAttempts — описание в ClientConfig

## Проблема
- Параметры `authRetryOn401` и `authRetryAttempts` используются в примере
  `../docs/features/authentication.md`, но не описаны в `ClientConfig`.

## Канонический источник
- `../docs/architecture/06-config.md`

## Рекомендуемое решение (с аргументацией)
- Добавить параметры в описание `ClientConfig` с дефолтами
  (например, `true` и `1`).
  Аргументы: сейчас поведение неявное и не воспроизводимо без кода.
- Указать, как они влияют на повтор запроса при 401.
