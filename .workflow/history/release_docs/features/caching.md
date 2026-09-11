# Кеширование

## Обзор

SDK поддерживает кеширование ответов с каскадной конфигурацией и возможностью переопределения в рантайме.

Важно: кеширование не ограничено HTTP-методом. При включенном кеше SDK может
кешировать ответы для любых методов; метод участвует в формировании ключа.

**Уровни (приоритет снизу вверх):**
```
Клиент (defaults) → Запрос (атрибут) → Рантайм (метод)
```

---

## Конфигурация через VO

```php
use Psr\SimpleCache\CacheInterface;

use Brahmic\ApiSutra\Enums\Cache\CacheMode;

$client = new Client(
    config: new ClientConfig(
        baseUrl: 'https://api.example.com',
        
        cache: new CacheConfig(
            store: $psrCache,      // PSR-16 совместимый
            ttl: 3600,             // default TTL (секунды)
            prefix: 'api_',        // префикс ключей
            mode: CacheMode::Enabled,
        ),
    )
);
```

### CacheConfig

```php
use Brahmic\ApiSutra\Enums\Cache\CacheMode;

readonly class CacheConfig
{
    public function __construct(
        public ?CacheInterface $store = null,
        public int $ttl = 3600,
        public string $prefix = '',
        public CacheMode $mode = CacheMode::Enabled,
    ) {}
}
```

По умолчанию кеширование отключено (без `cache` в конфиге).

---

## Уровень запроса (атрибуты)

```php
use Brahmic\ApiSutra\Enums\Cache\CacheMode;

// Кешировать с кастомным TTL
#[Cache(ttl: 300)]
class GetOrder extends AbstractRequest { }

// Отключить кеширование для этого запроса
#[Cache(mode: CacheMode::Disabled)]
class GetLiveStatus extends AbstractRequest { }

// Использовать TTL из конфига клиента
#[Cache]
class GetUser extends AbstractRequest { }
```

---

## Рантайм (методы)

### withoutCache()

Получить свежие данные без кеша:

```php
$result = $request->withoutCache()->send();
```

Поведение:
- Не читает из кеша
- Выполняет HTTP-запрос
- Не записывает результат в кеш

Для сценария «прочитать всегда свежие, но записать» используйте `withCacheWriteOnly()`.

### withCache(?int $ttl = null)

Включить кеширование или переопределить TTL:

```php
// Использовать TTL из конфига клиента
$result = $request->withCache()->send();

// Переопределить TTL
$result = $request->withCache(ttl: 60)->send();
```

Полезно когда запрос по атрибуту без кеша, но в моменте нужен.

### withCacheWriteOnly(?int $ttl = null)

Только запись в кеш без чтения:

```php
$result = $request->withCacheWriteOnly()->send();
```

### withCacheReadOnly(?int $ttl = null)

Чтение из кеша без записи:

```php
$result = $request->withCacheReadOnly()->send();
```

### clearCache()

Удалить кеш конкретного запроса:

```php
$request->clearCache();  // удаляет кеш по ключу этого запроса
```

---

## Сброс кеша на клиенте

```php
// Сбросить весь кеш SDK
$client->clearCache();
```

---

## Composite и Batch

- **Composite:** кеширование применяется к каждому вложенному запросу
  по его правилам (`#[Cache]`, with/withoutCache). Аггрегированный результат
  композита отдельно не кешируется.
- **Batch:** каждый запрос в batch кешируется по своим правилам, общий
  результат batch не кешируется как единый объект.

---

## Ключ кеша

Генерируется автоматически из:

```php
$cacheKey = $prefix . hash('xxh3', implode('|', [
    $method,      // GET, POST...
    $url,         // полный URL с path-параметрами
    $queryString, // отсортированные query params
    $bodyHash,    // для POST/PUT — хеш тела
]));
```

Гарантирует уникальность для каждой комбинации параметров.

---

## Поведение по умолчанию

| Ситуация | Читать кеш | Писать кеш |
|----------|------------|------------|
| Любой запрос при включенном кеше | ✅ | ✅ |
| `withoutCache()` | ❌ | ❌ |
| `withCache()` | ✅ | ✅ |
| `withCacheWriteOnly()` | ❌ | ✅ |
| `withCacheReadOnly()` | ✅ | ❌ |
| `#[Cache(mode: CacheMode::Disabled)]` | ❌ | ❌ |

---

## Пример

```php
// Клиент с кешем
$client = new Client(
    config: new ClientConfig(
        baseUrl: 'https://api.example.com',
        cache: new CacheConfig(
            store: new RedisCache(),
            ttl: 3600,
            prefix: 'myapi_',
        ),
    )
);

// Обычный запрос — кешируется
$order = $client->orders()->get('123')->send();

// Нужны свежие данные без кеша
$fresh = $client->orders()->get('123')->withoutCache()->send();

// Нужны свежие данные и обновить кеш
$freshAndCached = $client->orders()->get('123')->withCacheWriteOnly()->send();

// Запрос без кеша по атрибуту, но сейчас нужен
$data = $client->reports()->realtime()->withCache(ttl: 30)->send();

// Сбросить кеш конкретного запроса перед повторным выполнением
$client->orders()->get('123')->clearCache();
```

---

## PSR-16 совместимость

SDK использует PSR-16 (Simple Cache) интерфейс. Подходят:
- Symfony Cache
- Laravel Cache (через адаптер)
- любая PSR-16 реализация

---

## Резюме

| Элемент | Назначение |
|---------|------------|
| `CacheConfig` | VO конфигурации кеша |
| `#[Cache]` | Атрибут на запросе |
| `withoutCache()` | Свежие данные без кеша |
| `withCacheWriteOnly()` | Свежие данные + обновить кеш |
| `withCacheReadOnly()` | Только чтение из кеша |
| `withCache()` | Включить/переопределить TTL |
| `clearCache()` | Удалить кеш запроса |
