# Cache

Кеширование запросов через `CacheConfig` и `cache`.

## Базовая настройка
```php
use Brahmic\ApiSutra\Config\CacheConfig;
use Brahmic\ApiSutra\Config\ClientConfig;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    cache: new CacheConfig(ttl: 60, prefix: 'sdk'),
);
```

## CacheInterface vs CacheConfig
- `cache: CacheInterface` — короткая запись, создаёт `CacheConfig` с дефолтами.
- `cache: CacheConfig` — полный контроль (ttl/prefix/mode).
- `cacheConfig` можно передать отдельно, если `cache` — это store.

Дефолты `CacheConfig`:
- `ttl = 3600`, `prefix = ''`, `mode = Enabled`

Используйте `cache: CacheInterface`, когда вам достаточно дефолтов.
`CacheConfig` нужен, если хотите управлять TTL, prefix или режимами.

## Режимы кеша
`CacheMode`: Enabled / Disabled / ReadOnly / WriteOnly.  
Можно переопределять на уровне запроса (атрибут `#[Cache]`) или runtime‑опциями.

## Ключ кеша
Ключ строится из:
- HTTP‑метода
- baseUrl + endpoint
- query (с нормализацией порядка)
- body (хеш)

Префикс берётся из `CacheConfig::prefix`.

## Download/Upload нюансы
- **Download** кешируется только при явном opt‑in (атрибут/override).
- **Upload** с файлами **не кешируется**.

Opt‑in для download:
- атрибут `#[Cache]` на запросе
- runtime‑override `withCache()`

## clearCache
`clearCache()` удаляет запись по тому же ключу, что и чтение/запись.

## Где детали
- Атрибуты поведения: `docs/guides/attributes/behavior.md`

