# Аутентификация

## AuthenticatorInterface
Интерфейс стратегии аутентификации. Методы: authenticate (добавить auth к запросу), shouldRefresh (нужно ли обновить токен), getRefreshRequest (запрос для получения токена), processTokenResponse (обработать ответ с токеном).

## ApiKeyAuthenticator
Готовая реализация для API Key. Добавляет ключ в header или query параметр.

## BasicAuthenticator
Готовая реализация для Basic Auth. Добавляет Authorization header с base64(username:password).

## BearerAuthenticator
Готовая реализация для статичного Bearer токена.

## AuthorizationSchemeAuthenticator
Готовая реализация для схем `Authorization: Scheme key="value", ...`.
Параметры могут быть статическими или вычисляться через AuthorizationParamsProviderInterface.
Формат параметров задаётся через AuthorizationParamsFormatterInterface.

## AuthorizationParamsProviderInterface
Контракт для генерации параметров `Authorization` на основе PreparedRequest.

## AuthorizationParamsFormatterInterface
Контракт форматтера параметров `Authorization` (quoted/comma, query-like и т.п.).

## TokenAuthenticator
Реализация для динамических токенов (refresh по сроку жизни). Использует CacheAwareInterface.
Ожидает refresh‑запрос (`refreshRequestClass`) и DTO с полями `accessToken`/`expiresIn`.

## HmacAuthenticator
Реализация HMAC‑подписи запросов. Генерирует подпись на основе тела/метода/времени.

## NoAuth (атрибут)
Атрибут для публичных endpoints. SDK пропускает аутентификацию для запросов с этим атрибутом.

## withoutAuth()
Метод AbstractRequest. Отключает аутентификацию в рантайме для конкретного вызова.

## CacheAwareInterface
Интерфейс-маркер для authenticator'ов, которым нужен кеш. SDK автоматически вызывает setCache() при инициализации клиента, инжектя кеш из ClientConfig. Метод getCacheKey() задаёт ключ хранения токена.

## Примеры
См. `docs/guides/auth.md` — есть примеры для всех встроенных аутентификаторов.
