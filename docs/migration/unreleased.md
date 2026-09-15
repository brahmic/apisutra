# Миграция текущих изменений

Выпуск `v0.2.0-alpha.1` меняет поведение `v0.1.0-alpha.1` в перечисленных сценариях.
Обновление не полностью обратно совместимо. Проверьте строки, относящиеся к вашему
SDK; обязательных префиксов, RedactionPolicy или нового конфигурационного файла нет.

## Разделение store и параметров кеша

Вместо двух источников backend теперь используются независимые
`ClientConfig::cacheStore` и `ClientConfig::cacheConfig`. Это изменение совместимости.

**До (удалённый API):**

```php
$config = new ClientConfig(baseUrl: $url, cache: new CacheConfig(store: $store, ttl: 60));
```

**После:**

```php
$config = new ClientConfig(baseUrl: $url, cacheStore: $store, cacheConfig: new CacheConfig(ttl: 60));
$copy = $config->with(timeout: 7); // Store и параметры сохранены.
$short = $config->with(cacheConfig: new CacheConfig(ttl: 10)); // Только параметры.
$detached = $config->with(cacheStore: null); // Общий store отключён, записи не удаляются.
```

- `cache: $store` замените на `cacheStore: $store`. Перенесите `CacheConfig::store`
  в `ClientConfig::cacheStore`; в блоке остаются ttl, prefix, mode, identity, locks.
- Обновите named arguments, массивы для unpacking/fromLaravel, wrappers и чтение
  публичного свойства `$config->cache` на `$config->cacheStore`.
- Позиция cacheStore у ClientConfig совпадает с прежним cache. У CacheConfig удалён
  первый позиционный store; предпочтительны именованные параметры.
- Старые аргументы `cache:` и `CacheConfig(store: ...)` дают PHP Error;
  CacheConfig вместо cacheStore и backend вместо cacheConfig дают TypeError.
- `with(cacheConfig: null)` сбрасывает параметры к defaults и сохраняет store:
  Disabled снова станет Enabled. Полный сброс — оба поля null. Явные locks могут
  иметь собственный backend; отключение только store их не отключает.

Ключи, формат старых записей и алгоритмы identity не меняются, очистка при миграции
не нужна. Смена TTL влияет на новые записи. [Текущий контракт](../reference/execution/cache.md#копирование-и-отключение).

## После v0.2.0-alpha.1: ожидание и кеш метаданных

Эти исправления действуют и без внешнего набора правил:

- `await()` больше не определяет готовность успешной гидратацией DTO. Укажите
  `ContinuationResult::stateResolver`, непустой `unwrap` или клиентский
  `continuationStateResolver`; без критерия будет ошибка конфигурации до polling.
  Ошибка финального DTO завершает ожидание сразу. Для `awaitByTokenAs()` нужен
  клиентский resolver. При ручном создании `ContinuationService` передайте гидратор
  вторым аргументом. Порядок перехода и обработка исключений — в
  [миграции ожидания](../reference/execution/continuation-await.md#миграция-с-эвристического-ожидания).
- Объектные defaults конструктора и аргументы атрибутов больше не разделяются
  между DTO и запросами из-за кеша. Не используйте их как общее изменяемое состояние.
  Scalar/enum defaults и намеренно переданные общие объекты сохраняют поведение.
  Момент вычисления описан для [defaults](../reference/dto/lifecycle.md#значения-по-умолчанию-и-изоляция-объектов)
  и [аргументов Cast](../reference/serialization/casts.md).

## После v0.2.0-alpha.1: внешние правила DTO

Новый `ClientConfig::hydrationRules` включается явно. Без набора действуют прежние
правила после исправлений metadata cache и continuation. Для переноса plain-моделей,
отказа от неявных scalar conversions и сохранения неизвестных полей см.
[переход существующего SDK](../guides/dto/plain-models.md#переход-существующего-sdk).
Набор исключает объявленный receiver из запросов клиента, а `DTO::from()` и `toArray()`
не наследуют его автоматически.
