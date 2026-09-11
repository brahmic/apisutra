# План: авто‑discovery namespace запросов (вариант 3)

## Цель
Автоматически находить и регистрировать namespace запросов клиента с минимальной ручной настройкой.

## Принципы
- В проде — использовать кеш discovery (можно отключить).
- В локале/тестах — без кеша по умолчанию (можно включить).
- Скан по root‑namespace → fallback на конвенции → кеш.
- Ресурсы сканируются только при наличии явного контракта.

## Поведение по умолчанию
- В клиентском ServiceProvider достаточно **одной строки**: `registerAuto($client)`.
- Discovery делает скан **автоматически**, без ручного перечисления namespace.
- Режим кеша:
  - `Production`: кеш включён (быстрое стартап‑время).
  - `Local/Testing`: кеш выключен, всегда актуальный скан.

## Как работает скан (детали)
1) Определяется root‑namespace клиента (первые 2 сегмента).
2) Сканируются классы клиента (предпочтительно по classmap composer; при отсутствии — по PSR‑4 путям пакета).
3) Отбираются классы, которые `extends AbstractRequest`.
4) Собираются **уникальные namespaces** этих классов.
5) Результат сохраняется в кеш (если включён).

Если список пуст — бросаем понятную ошибку и предлагаем:
- задать explicit namespaces (или константу) в провайдере;
- или включить скан для окружения.

## Архитектура (сервисы)
1) `ClientDiscoveryService` — оркестратор регистрации.
2) `RequestNamespaceDetector` — формирует список namespace по правилам.
3) `RequestScanner` — находит классы запросов (`extends AbstractRequest`).
4) `ClassMapProvider` — отдаёт список классов (composer classmap/PSR‑4).
5) `ClientDiscoveryCache` — кеширует результат (PSR‑16).
6) `ClientRegistry` — сохраняет `namespace → client`.
7) `ClientResolver` — использует registry.

## Шаги реализации
1) Добавить `DiscoveryOptions` (кеш on/off, режим env, стратегию).
2) Реализовать `RequestNamespaceDetector`:
   - root‑scan по всем классам запросов;
   - fallback‑конвенции: `Root\\Requests`, `Root\\Resources\\**\\Requests`.
3) Реализовать `RequestScanner` и `ClassMapProvider`:
   - фильтр по `is_subclass_of(AbstractRequest::class)`.
4) Добавить `ClientDiscoveryCache`:
   - ключ: client class + версия пакета + checksum composer.
   - цель: стабильный ключ и авто‑инвалидация при обновлениях.
   - стратегии: off/auto/force.
5) Собрать `ClientDiscoveryService::registerAuto()`:
   - конвенция → если пусто, скан → кеш → registry.
6) Интеграция в provider клиента:
   - минимальный вызов `registerAuto($client, $options)`; без options — дефолтное поведение.
7) Защита ownership:
   - остаётся на уровне `ClientResolver`.

## Ресурсы (опционально)
Если нужен скан ресурсов — ввести `ResourceMapProviderInterface`:
`public function requests(): array`.
Только при его наличии включать ресурсный скан.
**Важно:** это будет аддитивно и без breaking changes.

## Когда нужна ручная настройка
- Если структура запросов **не попадает в скан** (например, закрыта автолоадом).
- Если нужно отключить скан/кеш в конкретном окружении.

**Как делать:**
- `register($client, $namespace)` — явно указать namespace.
- `registerAuto($client, DiscoveryOptions::forceOn/forceOff)` — управлять кешом.
- Опционально: `REQUEST_NAMESPACES`/интерфейс‑провайдер для списков namespace.

## Примеры / юзкейсы
- **По умолчанию:** запросы в `Root\\Requests` — всё работает без ручной регистрации.
- **Несколько папок:** `Root\\Resources\\Users\\Requests` + `Root\\Reports\\Requests` — auto‑scan найдёт оба.
- **Prod:** кеш включен, cold‑scan только при первом запуске.
- **Local:** кеш выключен, всегда актуальный скан.

## Финальные ожидания
- Разработчик **не перечисляет** namespace вручную в приложении.
- Множественные клиенты не конфликтуют.
- В проде discovery не замедляет запуск (кеш работает).
- Расширение под ресурсы возможно без ломки API.
