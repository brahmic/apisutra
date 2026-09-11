# Анализ провайдера

Перед реализацией провайдера зафиксируйте ключевые решения — это снизит число
переписываний и упростит поддержку.

## 1) Базовые параметры
- baseUrl и версии API
- обязательные заголовки и общие параметры
- формат ответов (JSON, XML, файлы)
- есть ли несколько **разных** API‑сервисов с отдельными baseUrl/auth
- различаются ли глобальные настройки (baseUrl/auth/pagination/serialization)

## 2) Аутентификация
- тип (API key, OAuth, HMAC)
- нужна ли ротация/refresh
- есть ли разные уровни доступа (scope)
- где передаётся токен (header/query), нужен ли только для части запросов
- есть ли подготовительные/системные запросы и зависимости между шагами
- как обрабатываются 401/403 и сколько попыток допустимо

Рекомендация: выносите auth‑логику в классы (`AuthenticatorInterface`,
`AuthPolicyInterface`) и передавайте их в `ClientConfig`, чтобы конфиг оставался тонким.

Сопоставление: `ClientConfig.auth`, `authScopes`, `AuthPolicy`, `AuthScope`.

## 3) Пагинация
- тип: offset или cursor
- где хранится meta и items
- лимиты по страницам
- нужны ли свои классы: `PaginationMetaResolver`, коллекция items, DTO‑контейнер

Сопоставление: `PaginationConfig`, `#[Pagination]`, `PaginationRule`.

## 4) Rate Limit и Retry
- лимиты по ключам и окнам (per‑token/per‑endpoint)
- поведение при превышении (wait/throw)
- какие статусы и исключения безопасно повторять

Сопоставление: `RateLimitConfig`, `RetryConfig`, `#[RateLimit]`, `#[Retry]`.

## 5) Ошибки и маппинг
- структура ошибок провайдера
- единая модель ошибок в SDK
- нужен ли типизированный контекст ошибок (traceId/target/hint); **`providerTraceId`** — только
  при подтверждённой поддержке трассировки провайдером (см. [provider-methodology](./provider-methodology.md))

Сопоставление: `ClientErrorMapperInterface`, `ErrorContextFactoryInterface`, `ResolvedResultFactoryInterface`.

## 6) DTO и сериализация
- naming strategy
- типы/форматы (даты, деньги, enums)
- требования к валидации

Сопоставление: `NamingStrategy`, `Cast`, `Validate`, `ClientConfig.casts`.

## 7) Файлы и архивы
- загрузка файлов (multipart/binary/base64)
- скачивание файлов и архивов

Сопоставление: `#[File]`, `#[Download]`, `ArchiveExtension`, `ArchiveConfig`.

## 8) Структура, DTO и enums
- структура папок и владение сущностями (ресурсы → запросы/DTO/enum)
- провайдер‑wide типы в `Domain/Dto` и `Domain/Enums`
- группировка DTO/enum по подпапкам при большом объёме
- базовые абстракции (BaseRequest/BaseDto/BaseResource)

Сопоставление: [Методология провайдера](./provider-methodology.md).

## Пример чек‑листа анализа
- [ ] baseUrl, версии API, отдельные домены
- [ ] несколько сервисов и различия глобальных настроек
- [ ] auth: тип, scopes, refresh, 401/403
- [ ] pagination: тип, meta/items, лимиты, нестандартные поля
- [ ] rate‑limit/retry: лимиты, коды/исключения
- [ ] ошибки: формат и маппинг
- [ ] DTO/serialization: naming, типы, касты, валидация
- [ ] файлы/архивы: upload/download
- [ ] структура SDK: ресурсы, DTO/enum, Domain‑слой, базовые абстракции
- [ ] sandbox/тестирование: baseUrl, креды, ограничения

## Итоговый артефакт
Сформируйте короткую карту решений и примените её в:
- `docs/guides/client-config/*`
- `docs/guides/attributes/*`

Если сервисов несколько и они существенно различаются,
рассмотрите мегаклиент: `docs/guides/megaclient.md`.

## Дальше
- [Методология провайдера](./provider-methodology.md)
