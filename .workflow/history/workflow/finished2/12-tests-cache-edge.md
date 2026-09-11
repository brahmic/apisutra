# План 12: тесты cache edge cases (P0)

## Проблема
Нет тестов для `clearCache`, коллизий ключей, TTL и override‑поведения.

## Рекомендованное решение
- Добавить тесты `CacheManager::clearCache()` и `resolveCacheOverride()`.
- Проверить стабильность `buildCacheKey()` и нормализацию query.
- Тестировать истечение TTL.

## Нюансы
- Для TTL использовать fake‑clock или контролируемый таймер.
