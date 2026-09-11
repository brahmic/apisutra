# План 01: синхронизация refresh токена (P0)

## Проблема
В `src/Auth/TokenAuthenticator.php` и `src/Pipeline/Auth/AuthHandler.php` refresh выполняется без блокировки. При параллельных запросах возможны одновременные refresh‑вызовы и гонки токенов.

## Рекомендованное решение
- Ввести per‑token lock (ключ: baseUrl + clientId/username).
- Захват lock через cache (`CacheInterface::add()`/`set()` с TTL).
- Если lock занят: ожидать и повторно читать токен; при обновлении — использовать новый.
- Освобождение lock в `finally`, ограничение `maxWaitMs`.

## Нюансы
- Должен работать без cache: fallback на in‑memory lock per‑client.
- TTL lock должен быть короче суммарного времени refresh.
- Не допускать deadlock при исключениях и early‑return.
