# План 04: jitter не должен превышать maxDelay (P1)

## Проблема
В `src/RetryHandler.php` jitter добавляется к рассчитанному delay без финального ограничения, возможен выход за `maxDelay`.

## Рекомендованное решение
- Считать base delay, затем jitter, затем применять `min(maxDelay, delay + jitter)`.
- Либо применять jitter до clamp и выполнять финальный clamp.
- Покрыть тестом.

## Нюансы
- Если `maxDelay` равен `null`, clamp не нужен.
- Jitter должен быть детерминированным в тестах (подмена random).
