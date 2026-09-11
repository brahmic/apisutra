# Пагинация

Механизм работы с пагинированными API.

## Обзор

SDK поддерживает:
- Одиночные запросы с пагинацией
- Массовую загрузку (все страницы, диапазон, первые N)
- Ленивую итерацию по страницам
- Разные форматы параметров (page/limit, offset/limit)

Дополнительно:
- `PaginationOptions` — отдельный VO для runtime‑параметров пагинации (page/limit/cursor).
- `Paginator` — мутабельный builder, настраивает процесс обхода страниц.

---

## PaginableInterface

Запрос с поддержкой пагинации реализует интерфейс:

```php
use Brahmic\ApiSutra\Contracts\Core\RequestExecutionInterface;

interface PaginableInterface
{
    public function withPage(int $page): RequestExecutionInterface;
    public function withLimit(int $limit): RequestExecutionInterface;
    
    // Опционально — для cursor-based
    public function withCursor(?string $cursor): RequestExecutionInterface;
    
    // Извлечение меты из ответа (приоритет над атрибутом)
    public function extractMeta(array $response): PaginationMeta;
}
```

---

## Конфигурация параметров

### ClientConfig (default)

```php
new ClientConfig(
    paginationConfig: new PaginationConfig(
        pageParam: 'page',    // имя параметра страницы
        limitParam: 'rows',   // имя параметра лимита
    ),
);
```

### Атрибут на запросе (переопределение)

```php
#[Pagination(
    pageParam: 'offset',
    limitParam: 'limit',
    metaPath: 'meta',           // путь к мете в ответе
    itemsPath: 'data.items',    // путь к данным
)]
class ListOrders extends AbstractRequest implements PaginableInterface
```

> Важно: `pageParam: 'offset'` **не** включает offset‑based логику автоматически.
> Для offset‑based нужно явно указать `offsetBased: true`.

---

## Извлечение меты

### Вариант A: атрибут (простые случаи)

```php
#[Pagination(metaPath: 'meta')]
class ListOrders extends AbstractRequest implements PaginableInterface

// Ответ API:
// { "meta": { "total": 150, "page": 1, "per_page": 20 }, "data": [...] }
```

### Вариант B: метод (сложные случаи)

Приоритет выше атрибута. Для случаев когда мета требует вычислений:

```php
class ListCases extends AbstractRequest implements PaginableInterface
{
    public function extractMeta(array $response): PaginationMeta
    {
        return new PaginationMeta(
            total: $response['count'],
            currentPage: $response['page'],
            perPage: $response['rows'],
            hasMore: $response['page'] < ceil($response['count'] / $response['rows']),
        );
    }
}
```

`extractMeta()` вызывается для **каждого ответа страницы**. При массовой
загрузке метод используется на каждой странице независимо.

---

## PaginationMeta

```php
readonly class PaginationMeta implements ResultMeta
{
    public function __construct(
        public ?int $total,          // общее количество записей
        public int $currentPage,     // текущая страница
        public int $perPage,         // записей на странице
        public bool $hasMore,        // есть ли ещё страницы
        public ?string $nextCursor = null,  // для cursor-based
    ) {}
    
    public function totalPages(): ?int
    {
        return $this->total ? (int) ceil($this->total / $this->perPage) : null;
    }
}
```

---

## PaginationOptions

Отдельный runtime‑VO, который хранит параметры пагинации:

```php
final readonly class PaginationOptions
{
    public function withPage(int $page): self;
    public function withLimit(int $limit): self;
    public function withCursor(?string $cursor): self;

    public function hasPage(): bool;
    public function getPage(): ?int;
    // аналогично для limit/cursor
}
```

Используется внутри `RequestExecution` и передаётся в pipeline отдельно от `RequestOptions`.

---

## Paginator (mutable builder)

`Paginator` хранит состояние обхода страниц (`page/limit/cursor/failStrategy`) и **мутируется**:
- `perPage()`, `failStrategy()`, `range()` меняют внутреннее состояние;
- результат формируется методами `all()/pages()/range()` или итерацией.

Это процессный builder, а не immutable‑VO.

---

## Использование

### Одиночный запрос

```php
$result = $client->orders()->list(page: 2, limit: 10)->send();

$result->data;         // данные страницы
$result->meta->total;  // общее количество
$result->meta->hasMore; // есть ли ещё
```

### Массовая загрузка

```php
// Все страницы
$result = $client->orders()->list()->paginate()->all();

// Первые N страниц
$result = $client->orders()->list()->paginate()->pages(5);

// Диапазон страниц
$result = $client->orders()->list()->paginate()->range(2, 10);

// С настройкой
$result = $client->orders()->list()
    ->paginate()
    ->perPage(50)
    ->failStrategy(FailStrategy::Partial)
    ->all();
```

### Ленивая итерация

```php
foreach ($client->orders()->list()->paginate() as $page) {
    // Каждая итерация = HTTP запрос
    process($page->data);
    
    if ($someCondition) {
        break;  // Остановиться, не загружая остальное
    }
}
```

---

## PaginatedResult

Результат массовой загрузки. Extends `ExecutionResult`:

```php
readonly class PaginatedResult extends ExecutionResult
{
    // Наследует от ExecutionResult:
    // - status, errors, debug, traceId, audit
    // - data (объединённые данные), nested (результаты страниц), meta
    // - isSuccess(), isPartial(), isFailed(), hasData(), hasErrors()
    
    // Специфичные методы:
    public function items(): array;           // $this->data — объединённые данные
    public function pages(): array;           // $this->nested — результаты страниц
    public function meta(): PaginationMeta;   // типизированная мета
}
```

### Пример

```php
$result = $client->cases()->paginate()->all();

if ($result->isPartial()) {
    // Часть страниц не загрузилась
    foreach ($result->errors as $error) {
        log("Страница {$error->context['page']} не загружена");
    }
}

// Работаем с тем что есть
foreach ($result->items as $item) {
    process($item);
}
```

---

## FailStrategy

Поведение при ошибке загрузки страницы:

| Стратегия | Поведение |
|-----------|-----------|
| `FailAll` | Весь запрос = ошибка |
| `Partial` | Вернуть успешные + errors |

```php
$result = $client->orders()->list()
    ->paginate()
    ->failStrategy(FailStrategy::Partial)
    ->all();
```

---

## Retry

Каждая страница проходит через стандартный pipeline:
- Retry настройки из `ClientConfig` применяются
- Rate Limiting соблюдается
- Caching работает (если включено)

**Два уровня:**
1. **Pipeline** — retry для каждого HTTP запроса страницы
2. **Paginator** — `FailStrategy` если страница не загрузилась после всех retry

---

## Типы пагинации

### Page-based (default)

```php
// ?page=2&per_page=10
#[Pagination(pageParam: 'page', limitParam: 'per_page')]
```

### Offset-based

```php
// ?offset=20&limit=10
#[Pagination(pageParam: 'offset', limitParam: 'limit', offsetBased: true)]
```

### Cursor-based

```php
// ?cursor=abc123&limit=10
#[Pagination(cursorParam: 'cursor', limitParam: 'limit')]
class ListEvents extends AbstractRequest implements PaginableInterface
{
    public function extractMeta(array $response): PaginationMeta
    {
        return new PaginationMeta(
            total: null,  // cursor-based часто без total
            currentPage: 0,
            perPage: $response['limit'],
            hasMore: $response['has_more'],
            nextCursor: $response['next_cursor'],
        );
    }
}
```

---

## Резюме

| Задача | Решение |
|--------|---------|
| Одна страница | `->send()` |
| Все данные | `->paginate()->all()` |
| Первые N страниц | `->paginate()->pages(N)` |
| Диапазон | `->paginate()->range(from, to)` |
| Итерация | `foreach (->paginate() as $page)` |
| Параметры | `ClientConfig` + `#[Pagination]` |
| Мета | Атрибут `metaPath` или метод `extractMeta()` |
| Ошибки | `FailStrategy::Partial` для partial results |
