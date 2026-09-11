# Provider Emulation — план тестирования

## Scope
- Эмуляция логики внешних поставщиков данных для тестового клиента.
- Сценарии синхронных и асинхронных проверок (polling).
- Статусы и коды — через enum, без «магических» строк.

## Цели
- Реалистичные кейсы для SDK без реальных API.
- Проверка pipeline/serialization/retry/polling на сложных сценариях.
- Единый подход к статусам и ошибкам.

## Маппинг статусов/кодов → ResultStatus (пер‑провайдер)
Важно: **у каждого провайдера своя семантика** статусов и кодов.  
Поэтому маппинг фиксируется **отдельно для каждого провайдера** в тестовых stubs.

Рекомендация по формату:
- В stubs хранить `ProviderAStatusMap` и `ProviderBStatusMap`.
- Тесты проверяют именно **их** правила, без глобальных допущений.
- Если есть «успешное завершение без данных» — явно фиксировать,
  это `SUCCESS` с пустыми данными или `PARTIAL` (в зависимости от провайдера).

## Провайдер A (синхронный)
### Эндпоинты (эмуляция)
- Базовая проверка (sync)
- Быстрый поиск по идентификатору (sync)

### Статусы и коды (enum)
- `ProviderResultCode`: `Ok`, `Warning`, `NoData`
- `ProviderErrorCode`: `InvalidInput`, `NotFound`, `ProviderError`, `Timeout`
- `ProviderSyncToken` (опц.) — строковый идентификатор операции (если провайдер возвращает token)

### Сценарии
- Sync success (200 + результат).
- Sync validation error (400 + errorCode).
- Sync not found (200 + `NoData`).
- Sync warning (200 + `Warning` → `PARTIAL`).
- Sync provider error (5xx / `ProviderError`).
- Sync timeout (`Timeout`).
- Sync response может содержать `operationToken` для трассировки/корреляции.

## Провайдер B (асинхронный / polling)
### Эндпоинты (эмуляция)
- Комплексная проверка (async polling)
- Проверка по реестру/статусу (async polling)
- Поиск заявок/запросов по фильтрам (search)
- Получение событий/ленты статусов (events)
- Получение бинарного результата (download)

### Статусы и коды (enum)
- `ProviderStatus`:
  `Accepted`, `InProgress`, `Ready`, `Failed`, `NotFound`,
  `ValidationError`, `PaymentRequired`, `Suspended`, `Rejected`, `Timeout`, `Canceled`
- `ProviderErrorCode`: `InvalidInput`, `NotFound`, `ProviderError`, `LimitExceeded`

### Сценарии
- Async: `Accepted` → `InProgress` → `Ready`.
- Async: `Accepted` → `Failed` (provider error).
- Async: `Accepted` → `ValidationError` (ошибка валидации входных данных).
- Async: `Accepted` → `PaymentRequired` → `Ready` (эмулируем оплату).
- Async: `Accepted` → `Suspended` → `Ready` (временная пауза).
- Async: `Accepted` → `Timeout` / `Canceled`.
- Async: `Accepted` → `Rejected`.
- Async: `Accepted` → `NotFound`.
- Polling: после терминального статуса дальнейшие опросы не меняют результат.
- Events: выдаёт ленту статусов по запросу.
- Events: повтор/дублирование статуса (идемпотентность обработки).
- Search: пагинация и пустой результат.
- Download: результат возвращается как бинарный контент.
- Download: «не готово» → затем успешный бинарный контент.

## Fixtures/Mocks
- `MockResponse::sequence` для async‑сценариев.
- JSON‑fixtures с типовыми ответами (sync/async).
- Бинарный fixture для download‑сценариев.
- Заглушки Request/DTO для каждого сценария.

## Priority
- P0: async polling + status enums.
- P1: sync сценарии + error codes + search/events/download.
