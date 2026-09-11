# План: глубокая декомпозиция RetrySender

## Цель
Разделить retry‑логику на отдельные компоненты без изменения поведения.

## Декомпозиция
1) `RetryConfigResolver`
   - Формирование RetryConfig (config + атрибут + override)

2) `RetryDecisionMaker`
   - shouldRetry / isRetryException
   - проверка RetryableException

3) `RateLimitApplier`
   - resolveRateLimitConfig + acquire

4) `DelayApplier`
   - applyDelay по config/override

## Нюансы
- `authRetryOn401` остаётся отдельным механизмом
- общий retry не применяется для 401
- порядок действий не меняется

## Шаги реализации
1. Вынести конфигурацию retry в `RetryConfigResolver`.
2. Вынести решение о повторе в `RetryDecisionMaker`.
3. Вынести delay/rate limit в отдельные аплайеры.
4. Переподключить `RetrySender`.

## Критерии приёмки
- `RetrySender` стал компактным.
- Поведение совпадает с текущим.
