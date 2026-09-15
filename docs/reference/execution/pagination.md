# Пагинация

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

Практическая [конфигурация провайдера](../../guides/recipes/pagination.md#пример-конфигурации-провайдера-с-пагинацией)
показывает применение этих параметров.

## Offset‑based
Если `offsetBased = true`, `page` превращается в offset.
Требуется `limit`, иначе будет исключение.

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

## Ошибки, защитная остановка и миграция

Ошибка страницы сохраняет исходный SDK-код, HTTP-ответ и context.page. Если первая
страница дала 20 объектов, а следующая вернула 403, агрегат содержит PARTIAL, эти
20 объектов и `forbidden` с настоящим 403. Раньше верхний уровень мог показывать
`server_error` без ответа; обработчики должны учитывать фактические коды.

FailAll останавливает обход. Partial может продолжить page/offset-пагинацию, но
cursor-пагинация останавливается после ошибки: следующего cursor нет. Любой повтор
уже посещённого cursor останавливает обход до повторной отправки. Значение `"0"`
сохраняется. `maxPages=null` отключает количественный предел, сохраняя защиту от цикла;
стандартный maxPages остаётся 1000. Непрозрачный cursor не попадает в context ошибки.

Защитная остановка сообщает `execution_error` с `pagination_stalled` или
`pagination_max_pages_reached`. Iterator выдаёт один дополнительный FAILED результат
после успешных страниц и завершается; это не HTTP-вызов. Проверяйте `isFailed()` перед
чтением данных страницы. Исходную ошибочную страницу iterator повторно не выдаёт.
`pages(N)` и `range()` сохраняют обычное ограничение выборки без дополнительной ошибки.

## Внешние правила DTO

С `ClientConfig::hydrationRules` элементы `itemsType` гидратируются одним набором
как в режиме контейнера, так и items-only. Без набора items-only сохраняет прежний
raw-результат. Правила контейнера и элемента разрешаются по их классам отдельно.
[Подключение и диагностика](../dto/diagnostics.md#диагностика-и-входы).
