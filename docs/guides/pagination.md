# Пагинация

Пагинация доступна для запросов, реализующих `PaginableInterface`
(обычно через `AbstractPaginatedRequest`).

## Базовая структура
```php
use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Pagination\AbstractPaginatedRequest;

#[Get('/users')]
final class ListUsers extends AbstractPaginatedRequest {}
```

## Выполнение
```php
$result = (new ListUsers())->paginate()->all();
$result = (new ListUsers())->paginate()->pages(3);
$result = (new ListUsers())->paginate()->range(2, 5);
```

## Настройка схемы пагинации
```php
use Brahmic\ApiSutra\Attributes\Behavior\Pagination;

#[Pagination(
    pageParam: 'page',
    limitParam: 'per_page',
    itemsPath: 'data.items',
    metaPath: 'meta',
    offsetBased: true,
    cursorParam: 'cursor',
    maxPages: 100,
)]
final class ListUsers extends AbstractPaginatedRequest {}
```

Дополнительные поля:
- `itemsType` — тип элемента для коллекции
- `itemsCollection` — класс коллекции items
- `itemsCollectionFactory` — фабрика коллекции items

## Правила исполнения (PaginationRule)
```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Pagination\PaginationRule;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    paginationRule: PaginationRule::all(),
);

$result = (new ListUsers())
    ->rules(PaginationRule::pages(2))
    ->send()
    ->raw();
```

## PaginationMeta и резолв
Метаданные извлекаются в приоритете:
1) `PaginationMetaOverrideInterface` на запросе  
2) `PaginationConfig::metaResolver` (класс или инстанс)  
3) `DefaultPaginationMetaResolver`

`PaginationMeta` содержит `total`, `currentPage`, `perPage`, `hasMore`, `nextCursor`.

По умолчанию `DefaultPaginationMetaResolver` ищет поля:
- `total` / `count`
- `page` / `currentPage` / `current_page`
- `per_page` / `perPage` / `limit` / `page_size`
- `next_cursor` / `nextCursor`
- `has_more` / `hasMore`

Если мета лежит в других полях или нужна дополнительная логика — нужен кастомный резолвер.

### Кастомный meta‑resolver
Если поля meta нестандартны, выделите свой `PaginationMetaResolverInterface`:
```php
use Brahmic\ApiSutra\Contracts\Interfaces\Pagination\PaginationMetaResolverInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Config\PaginationConfig;
use Brahmic\ApiSutra\VO\Metadata\PaginationMeta;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final class CustomPaginationMetaResolver implements PaginationMetaResolverInterface
{
    public function resolve(
        AbstractRequest $request,
        array $response,
        array $meta,
        PaginationConfig $config,
        ?PipelineContext $context
    ): PaginationMeta {
        return new PaginationMeta(
            total: isset($meta['count']) ? (int) $meta['count'] : null,
            currentPage: (int) ($meta['page'] ?? 1),
            perPage: (int) ($meta['rows'] ?? 0),
            hasMore: null,
            nextCursor: null,
        );
    }
}

#[Pagination(metaResolver: CustomPaginationMetaResolver::class)]
final class ListUsers extends AbstractPaginatedRequest {}
```

### Типизация items и коллекция
Если нужен типизированный набор и своя коллекция:
```php
use Brahmic\ApiSutra\Contracts\Interfaces\Pagination\PaginationItemsCollectionFactoryInterface;

final class UserCollectionFactory implements PaginationItemsCollectionFactoryInterface
{
    public function make(array $items): array|object
    {
        return new UserCollection($items);
    }
}

final class UserCollection
{
    public function __construct(
        private array $items,
    ) {}

    public function toArray(): array
    {
        return $this->items;
    }
}

#[Pagination(
    itemsType: UserDto::class,
    itemsCollectionFactory: UserCollectionFactory::class,
)]
final class ListUsers extends AbstractPaginatedRequest {}
```

Если `itemsType`/`itemsCollection*` не заданы, по умолчанию возвращается массив.

## Контейнер с meta + items
Чтобы сохранить мету в DTO‑обёртке, используйте:
- `PaginationItemsContainerInterface`
- или `AbstractPaginationContainerDto`

Тогда пагинатор извлечёт items через `items()` и сможет подставить их обратно.

### DTO‑контейнер для paginated‑ответа
```php
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\DataTransfer\AbstractPaginationContainerDto;

final readonly class PagedUsersDto extends AbstractPaginationContainerDto
{
    public function __construct(
        public int $count,
        public int $page,
        public int $rows,
        array|object|null $items = null,
    ) {
        parent::__construct(items: $items);
    }

    public function withItems(array|object $items): static
    {
        return new static(
            count: $this->count,
            page: $this->page,
            rows: $this->rows,
            items: $items,
        );
    }
}

#[Returns(PagedUsersDto::class)]
final class ListUsers extends AbstractPaginatedRequest {}
```

## PaginatedResult
Результат пагинации:
- `items()` — все элементы
- `pages()` — коллекция результатов по страницам
- `meta()` — `PaginationMeta`

## Guard и лимиты
- `PaginationConfig::maxPages` — жёсткий лимит страниц
- guard проверяет прогресс page/cursor, чтобы остановить бесконечные циклы

## Где детали
- `docs/guides/attributes/behavior.md` — `Pagination`
- `docs/guides/client-config/pagination.md`
- `docs/glossary/pagination.md`
