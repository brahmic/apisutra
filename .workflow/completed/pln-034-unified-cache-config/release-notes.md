Предварительный выпуск с единым блоком настройки кеша и неизменяемым копированием его полей.

### Изменения

- Подключение и параметры собраны в `ClientConfig::cacheConfig`: store передаётся через `CacheConfig::store`. Отдельный аргумент `cacheStore` удалён.
- Добавлен `CacheConfig::with()`: можно заменить TTL, режим, store, identity или locks, сохранив остальные поля и ссылки на зависимости.
- `ClientConfig::with(cacheConfig: ...)` заменяет блок целиком; передача `null` убирает подключение и все параметры у копии. Пустое копирование и изменение других настроек сохраняют блок.
- HTTP и auth используют один store. Копирование не очищает записи и не меняет существующий клиент; ключи и формат сохранённых HTTP-ответов и токенов сохранены.
- Обновлены справочник и исполняемые примеры конфигурации. В выпуск также вошли файловые примеры и дополнительные сценарии сериализации в обзоре DTO.

### Миграция с v0.4.0-alpha.1

**Изменение несовместимо с API 0.4.** Перенесите `cacheStore:` внутрь `CacheConfig`:

```php
$cache = new CacheConfig(store: $store, ttl: 60);
$config = new ClientConfig(baseUrl: $url, cacheConfig: $cache);
$short = $config->with(cacheConfig: $cache->with(ttl: 10));
$detached = $config->with(cacheConfig: null);
```

Для частичного изменения нужен `CacheConfig::with()`. Полная замена на блок без store теперь отключает подключение. `with(cacheConfig: null)` больше не сохраняет прежний store.

[Руководство миграции](https://github.com/brahmic/apisutra/blob/v0.5.0-alpha.1/docs/migration/v0.5.0-alpha.1.md) описывает также позиционные аргументы и сохранение явного locks при `store: null`.

### Установка

```bash
composer require "brahmic/apisutra:^0.5@alpha"
```

[Changelog](https://github.com/brahmic/apisutra/blob/v0.5.0-alpha.1/CHANEGLOG.md) · [Все изменения с v0.4.0-alpha.1](https://github.com/brahmic/apisutra/compare/v0.4.0-alpha.1...v0.5.0-alpha.1).
