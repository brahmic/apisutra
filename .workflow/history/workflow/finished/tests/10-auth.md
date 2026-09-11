# Auth — план тестирования

## Scope
- `AuthHandler`
- Authenticators: ApiKey, Basic, Bearer, Token, Hmac
- `CacheAwareInterface` интеграция

## Invariants
- `#[NoAuth]` и `withoutAuth()` отключают аутентификацию.
- `authRetryOn401` инициирует refresh token до `authRetryAttempts` раз.

## Unit tests
- Каждый authenticator формирует заголовки/подпись корректно.
- `TokenAuthenticator` использует cache key и TTL.

## Integration tests
- Refresh token flow: 401 → refresh → повтор.
- `CacheAwareInterface` получает кеш из клиента.

## Edge cases
- Нет auth в ClientConfig → запросы без auth.
- Ошибка refresh → проброс исключения.

## Fixtures/Mocks
- Mock responses для 401/200.
- In‑memory cache.

## Priority
- P0: refresh flow.
- P1: cache aware.
