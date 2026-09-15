# Контракт единого блока кеша

Решение принято владельцем в поручении на реализацию, [ADR-005](../../adr/adr-005-unified-cache-config.md).

## API

ClientConfig принимает только `?CacheConfig $cacheConfig = null`; поле/аргумент
cacheStore удаляется, cache не возвращается. cacheConfig занимает девятую позицию
(после logLevel); все последующие параметры сдвигаются на одну позицию влево.
CacheConfig остаётся final readonly: ttl, prefix, mode, identity, locks в прежнем
порядке, новый `?CacheInterface $store = null` добавляется последним. Это сохраняет
позиционные вызовы CacheConfig из 0.4; для ClientConfig требуется миграция.

`CacheConfig::with(mixed ...$overrides): self` создаёт новый блок. Отсутствующее поле
сохраняется; явный null устанавливается только для nullable полей store/identity/locks.
Скалярные поля и mode не принимают null. Неизвестные имена дают PHP Error, неверные
типы — TypeError. Семантика та же, что у ClientConfig::with(); позиционные overrides
не поддерживаются. Ссылки на store/identity/locks сохраняются без clone/IO.

## Замена и отключение

| Операция | Результат |
| --- | --- |
| ClientConfig::with(), with(timeout: 7) | Тот же блок и зависимости |
| with(cacheConfig: $replacement) | Полная замена блока, без наследования старого store/параметров |
| with(cacheConfig: null) | Общий store и все настройки, включая явный locks, убраны у копии |
| CacheConfig::with(ttl: 10) | TTL меняется, остальные значения и store сохраняются |
| CacheConfig::with(store: $other) | Меняется только store |
| CacheConfig::with(store: null) | Store отключён, параметры и явный locks сохранены |
| CacheConfig::with(mode: Disabled) | Выключен HTTP-кеш по умолчанию, auth использует store |
| new CacheConfig() | Defaults, без store; подключение не наследуется |

Отключение общего store не очищает данные, не меняет исходный клиент и не запрещает
отдельный backend явного locks provider, rate-limit или пользовательского расширения.
Без store request override Enabled не подключает хранилище. HTTP/auth получают store
только из клиентского блока. Применение #[Cache] копирует блок, сохраняя зависимости;
ключи, форматы данных, scoped invalidation и приоритеты режимов не меняются.
fromLaravel и array unpacking принимают тот же единственный блок; старые cacheStore
и cache дают Error, включая null. Скрытого fallback, legacy aliases и слияния нет.
