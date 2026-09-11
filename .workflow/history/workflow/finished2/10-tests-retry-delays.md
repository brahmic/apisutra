# План 10: тесты retry backoff/jitter/Retry-After (P0)

## Проблема
Нет тестов для backoff‑стратегий, jitter и `Retry-After`.

## Рекомендованное решение
- Добавить тесты `RetryHandler::calculateDelay()` для Constant/Linear/Exponential.
- Проверить jitter и ограничение `maxDelay`.
- Добавить тест `RetrySender` на обработку `Retry-After` (429).

## Нюансы
- Для jitter использовать детерминизм (подмена random/инъекция генератора).
