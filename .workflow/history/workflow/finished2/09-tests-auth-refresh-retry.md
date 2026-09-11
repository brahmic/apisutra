# План 09: тесты auth refresh и authRetryOn401 (P0)

## Проблема
Нет тестов для refresh‑механизма и поведения `authRetryOn401`.

## Рекомендованное решение
- Добавить unit‑тесты для `AuthHandler::refreshToken()` (успех/ошибка).
- Добавить тесты `RetrySender` для 401: refresh‑сначала, общий retry не применяется.
- Проверить счетчик `authRetryAttempts`.

## Нюансы
- Использовать stub authenticator с фиксацией вызовов.
- Покрыть сценарий с атрибутом `NoAuth`.
