# Пагинация

Фрагменты предполагают SDK с ресурсом `users()`, пагинированной операцией `list()`
и его DTO. Правила выполнения описаны в [справочнике](../../reference/execution/pagination.md).

```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\PaginationConfig;
use Brahmic\ApiSutra\Pagination\PaginationRule;
```

## Получить результат

### send() на пагинированном запросе
```php
$handle = $client->users()->list()->send();
$raw = $handle->raw();
```
Тип: `ResultHandle` → `ExecutionResult` (одна страница по умолчанию)

### Одна страница (default)
```php
$resolved = $client->users()->list()->resolved();
```
Тип: `ResolvedResult<UserListDto>`

### Все страницы
```php
$resolved = $client->users()
    ->list()
    ->rules(PaginationRule::all())
    ->resolved();
```
Тип: `ResolvedResult<UserCollection>`

```php
$raw = $client->users()
    ->list()
    ->rules(PaginationRule::all())
    ->send()
    ->raw();
```
Тип: `PaginatedResult`

### Диапазон страниц
```php
$resolved = $client->users()
    ->list()
    ->rules(PaginationRule::range(2, 4))
    ->resolved();
```
Тип: `ResolvedResult<UserCollection>`

### Только N страниц
```php
$resolved = $client->users()
    ->list()
    ->rules(PaginationRule::pages(3))
    ->resolved();
```
Тип: `ResolvedResult<UserCollection>`

## Правило по умолчанию в ClientConfig

### Клиент с правилом pages(2)

`$transport` — транспорт, выбранный при [сборке клиента](../integration/standalone.md).
```php
$client = new MyClient(
    config: new ClientConfig(
        baseUrl: 'https://api.test',
        paginationRule: PaginationRule::pages(2),
    ),
    transport: $transport,
);

$resolved = $client->users()->list()->resolved();
```
Тип: `ResolvedResult<UserCollection>`

### Локальное переопределение правила
```php
$resolved = $client->users()
    ->list()
    ->rules(PaginationRule::range(3, 4))
    ->resolved();
```
Тип: `ResolvedResult<UserCollection>`

## Итератор страниц
```php
$result = (new ListUsers())
    ->paginate()
    ->all();

$items = $result->items();
```

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
