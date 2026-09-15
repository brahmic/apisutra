# Миграция на отдельный cacheStore

Приложение [плана 033](pln-033-readme.md). Переход принят по
[ADR-004](../../adr/adr-004-cache-store-separation.md); здесь действия потребителя,
полный будущий контракт — в [contracts](contracts.md).
До реализации этот API не доступен. При выпуске перенести инструкции в публичную
миграцию и актуализировать все исполняемые примеры в той же версии.

## Создание клиента

Единственное хранилище передаётся через ClientConfig.cacheStore. CacheConfig
сохраняет ttl, prefix, mode, identity и locks, но больше не содержит store.

| Старый способ | Новый способ |
| --- | --- |
| cache: S | cacheStore: S |
| cache: C со store=S | cacheStore: S, cacheConfig: P без store |
| cache: S, cacheConfig: C без store | cacheStore: S, cacheConfig: тот же блок параметров |
| Только cacheConfig: C со store=S | cacheStore: S, cacheConfig: P без store |
| cache: C без store | cacheConfig: P; cacheStore остаётся null |
| Оба источника с одним S | Один cacheStore: S и один блок параметров |
| Разные store или разные цельные блоки | Выбрать нужный store и набор параметров явно; автоматического приоритета нет |

Для старого цельного блока пример меняется так:

```php
use Brahmic\ApiSutra\Config\CacheConfig;
use Brahmic\ApiSutra\Config\ClientConfig;

// Было.
$config = new ClientConfig(
    baseUrl: 'https://api.example.test',
    cache: new CacheConfig(store: $store, ttl: 60, prefix: 'sdk'),
);

// Станет.
$config = new ClientConfig(
    baseUrl: 'https://api.example.test',
    cacheStore: $store,
    cacheConfig: new CacheConfig(ttl: 60, prefix: 'sdk'),
);
```

Если были identity/locks/mode, перенести их без изменения и с теми же объектами.
Не заменять общие зависимости новыми экземплярами ради переименования аргумента.

## Копирование и отключение

| Намерение в старом коде | Новый вызов |
| --- | --- |
| with() / with(timeout: 7) | Синтаксис тот же; store и настройки сохраняются |
| Сменить только store | with(cacheStore: S2) |
| Заменить TTL и остальные параметры | with(cacheConfig: new CacheConfig(ttl: 10)) |
| Передать новый цельный блок вместе с store | with(cacheStore: S2, cacheConfig: P2) |
| Старый цельный блок без store должен убрать подключение | with(cacheStore: null, cacheConfig: P2) |
| Сбросить параметры, оставив store | with(cacheConfig: null) |
| Отключить подключение общего store | with(cacheStore: null) |
| Сбросить и подключение, и параметры | with(cacheStore: null, cacheConfig: null) |

Не выполнять слепую замену `cache` → `cacheStore` там, где передан CacheConfig.
Новый аргумент принимает только store; блок нужно вынести отдельно. Если прежний
cacheConfig включал новый store, при копировании теперь явно меняются оба поля.

Прежний with(cache:null) зависел от формы передачи store. В новой версии такого
аргумента нет. Если старый вызов намеренно сохранял подключение через блок,
не добавлять cacheStore:null: достаточно оставить store или изменить только настройки.
Если намерение было отключить его — явно использовать cacheStore:null.

Режим Disabled и withoutCache отключают только HTTP в своих прежних областях.
Удаление cacheStore отключает общее подключение HTTP/auth, сохраняя P; явный
locks provider в P остаётся активным. Оба null сбрасывают и его. Оригинальная
конфигурация и записи backend при этих операциях не изменяются.

## Прочие формы вызова и поставка

- Чтение `$config->cache` заменить на `$config->cacheStore`. Чтение `$settings->store`
  заменить явной зависимостью от клиентского cacheStore: источник больше не в P.
- Массивы конфигурации, unpacking и `ClientConfig::fromLaravel()` используют те же
  новые имена. Преобразования старых ключей в Laravel-адаптере не добавляются.
- Позиционные вызовы ClientConfig сохраняют место аргумента store, но значение
  CacheConfig в нём недопустимо. У CacheConfig удалён первый аргумент store;
  переводить такие вызовы на именованные ttl/prefix/mode/identity/locks и сверять порядок.
- Сигнатуры клиентских фабрик/обёрток SDK, передающие настройки дальше, обновляются
  одновременно. Шаблоны dependency injection тоже не должны передавать второй store.
- Нет переходного алиаса cache, magic getters и автоматической миграции сохранённых
  объектов ClientConfig/CacheConfig. Конфигурацию заново создать после обновления кода;
  долгоживущие workers перезапустить штатным способом.
- Номер релиза заранее не назначается. Указать несовместимость в CHANEGLOG,
  публичном migration/unreleased и реестре API; все пакуемые примеры перевести
  одновременно. Старые входящие issue/probe и завершённые доказательства сохранить.

## Как подтвердить перенос

Перенесённый пример должен показать: создание S/P → копия с timeout → два GET
при пустом store дают один HTTP; повторная копия использует существующую запись.
Отдельно проверить новый TTL, отключение store, HTTP-only Disabled и auth token cache.
Миграция не очищает backend и не меняет схему ключей; сохранённый раньше ответ
остаётся пригодным при тех же параметрах/identity и сроке жизни.

Оригинальный upstream-probe после удаления старых аргументов не запускается как
целевая проверка. До кода сохранить его вывод на исходном commit, затем создать
отдельную адаптацию под новую сигнатуру. Сопоставить изменения синтаксиса и
семантические ожидания по U01–U14 с матрицей C01–C26; снимок ошибок не является
ожидаемым результатом исправления. Исходник автора не редактировать.
