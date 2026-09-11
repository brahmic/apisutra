# Pagination

Дефолтные правила пагинации в `ClientConfig::paginationRule`.

## Базовая настройка
```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Pagination\PaginationRule;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    paginationRule: PaginationRule::all(),
);
```

По умолчанию `paginationRule = PaginationRule::single()` — пагинация не включается,
пока вы не зададите правило или не вызовете `paginate()`.

## Переопределение на запросе
- Атрибут `#[Pagination]` — конфигурация пагинации (пути, поля).
- Runtime‑override: `rules(PaginationRule::pages(2))`.

## PaginationConfig (детали)
```php
use Brahmic\ApiSutra\Config\PaginationConfig;

$config = $config->with(paginationConfig: new PaginationConfig(
    pageParam: 'page',
    limitParam: 'per_page',
    cursorParam: 'cursor',
    metaPath: 'meta',
    itemsPath: 'data.items',
    offsetBased: false,
    maxPages: 500,
));
```

Дефолты `PaginationConfig` (если не задан):
- `pageParam = page`, `limitParam = limit`, `cursorParam = null`
- `metaPath = meta`, `itemsPath = data`
- `offsetBased = false`, `maxPages = 1000`

Ключевые поля:
- `itemsType` — тип элемента для коллекции
- `itemsCollection` — класс коллекции items
- `itemsCollectionFactory` — фабрика коллекции items
- `metaResolver` — собственный резолвер меты

Рекомендация:
- если meta‑поля нестандартны — выделите класс `PaginationMetaResolverInterface`
- если нужен типизированный набор items — используйте `itemsCollection` или `itemsCollectionFactory`
- если хотите вернуть обёртку с meta+items — используйте DTO‑контейнер на базе `AbstractPaginationContainerDto`

`itemsCollection` подходит, если коллекцию можно создать через `fromArray()`/`make()`/`__construct`.
`itemsCollectionFactory` используйте для сложной инициализации (зависимости, валидация).

## Пример конфигурации провайдера с пагинацией
```php
use Brahmic\ApiSutra\Contracts\Interfaces\Pagination\PaginationMetaResolverInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Pagination\PaginationItemsCollectionFactoryInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\VO\Metadata\PaginationMeta;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final class ProviderPaginationMetaResolver implements PaginationMetaResolverInterface
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

final class ProviderItemsCollectionFactory implements PaginationItemsCollectionFactoryInterface
{
    public function make(array $items): array|object
    {
        return new ProviderItemCollection($items);
    }
}

final class ProviderItemCollection
{
    public function __construct(
        private array $items,
    ) {}

    public function toArray(): array
    {
        return $this->items;
    }
}

$config = $config->with(
    paginationConfig: new PaginationConfig(
        metaPath: 'response',
        itemsPath: 'response.result',
        itemsType: ItemDto::class,
        itemsCollectionFactory: ProviderItemsCollectionFactory::class,
        metaResolver: ProviderPaginationMetaResolver::class,
    ),
    paginationRule: PaginationRule::all(),
);
```

## Offset‑based
Если `offsetBased = true`, `page` превращается в offset.
Требуется `limit`, иначе будет исключение.

## Где детали
- Атрибуты поведения: `docs/guides/attributes/behavior.md`
- Пагинация: `docs/glossary/pagination.md`

