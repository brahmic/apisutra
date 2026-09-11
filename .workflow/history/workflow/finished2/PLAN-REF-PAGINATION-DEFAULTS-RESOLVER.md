# План: централизованные defaults пагинации и meta‑resolver

## Проблема
Сейчас `extractMeta()` приходится дублировать в каждом paginated‑запросе, даже при стандартном формате ответа.  
Также параметры `#[Pagination(...)]` (page/limit/meta/items) повторяются из запроса в запрос, даже если они одинаковы для провайдера.  
Это создаёт риск расхождений, усложняет DX и затрудняет поддержку.

## Цель
Дать возможность задать общие правила пагинации и извлечения meta на уровне клиента,  
с точечным переопределением на уровне запроса, без дублирования логики.

## Рекомендованное решение
1) **Client‑level config**: ввести `PaginationConfig` в `ClientConfig` для общих параметров пагинации.  
2) **Meta‑resolver**: добавить `PaginationMetaResolverInterface` и поддержать конфиг + атрибут.  
3) **Опциональный метод с максимальным приоритетом**: разрешить запросу переопределять meta‑логику методом, получающим `response` и `PipelineContext`.  
4) **Приоритеты**: метод запроса → `#[Pagination(metaResolver: ...)]` → `ClientConfig.paginationConfig->metaResolver` → дефолтный resolver.

## Основные шаги
1. **PaginationConfig**
   - Создать `Config\PaginationConfig` (VO) с полями: `pageParam`, `limitParam`, `cursorParam`, `metaPath`, `itemsPath`, `offsetBased`, `metaResolver`.
   - Добавить в `ClientConfig` свойство `paginationConfig: ?PaginationConfig` и поддержку в `with()`/`fromLaravel()`.
   - Не использовать `PaginationConfigVO` (это другой проект).
2. **Attribute Pagination**
   - Сделать поля `pageParam/limitParam/cursorParam/metaPath/itemsPath/offsetBased` nullable, чтобы частичные override не затирали defaults.
   - Добавить опциональный `metaResolver: ?string` (class‑string `PaginationMetaResolverInterface`).
3. **Resolver‑контракт**
   - Добавить `PaginationMetaResolverInterface` с методом `resolve(AbstractRequest $request, array $response, ?PipelineContext $context): PaginationMeta`.
   - Реализовать `DefaultPaginationMetaResolver` (перенести текущую эвристику из `RequestPaginationHelper::extractMeta`).
4. **Опциональный метод override**
   - Ввести интерфейс `PaginationMetaOverrideInterface` (имя можно уточнить) с методом
     `resolvePaginationMeta(array $response, array $meta, PaginationConfig $config, ?PipelineContext $context): PaginationMeta`.
   - `RequestPaginationHelper` проверяет его первым и использует приоритетно.
5. **RequestPaginationHelper**
   - Добавить `resolvePaginationConfig()` (слияние: defaults → атрибут).
   - В `extractMeta()` реализовать цепочку resolver‑ов по приоритету.
   - Использовать `PaginationConfig` при расчёте `page/limit/cursor` в `resolvePaginationParam()`.
6. **ResponseHydrator**
   - В `applyPagination()` использовать объединённую конфигурацию (itemsPath из defaults/атрибута).
7. **Тесты (ядро)**
   - Приоритет resolver‑ов (method > attribute > config > default).
   - Мерж defaults + частичные override в `#[Pagination]`.
   - Использование `itemsPath/metaPath` из defaults при отсутствии атрибута.
   - Регрессия: поведение без defaults остаётся прежним.

## Критерии приёмки
- Можно задать единые правила пагинации на уровне клиента.
- Можно задать meta‑resolver на уровне клиента или через `#[Pagination]`.
- Метод‑override запроса имеет максимальный приоритет.
- Без defaults поведение полностью совпадает с текущим.

## Ожидаемый результат
- Параметры пагинации для провайдера задаются один раз в `ClientConfig`.
- `extractMeta()` больше не дублируется в каждом запросе без необходимости.
- Частичный override в `#[Pagination]` не ломает общие defaults.
- Для нестандартных ответов используется единый meta‑resolver (клиент или атрибут).

## Примеры конфигурации и поведения

### 1) Общие defaults на уровне клиента
```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\PaginationConfig;

$config = new ClientConfig(
    baseUrl: 'https://api.example.com',
    paginationConfig: new PaginationConfig(
        pageParam: 'page',
        limitParam: 'rows',
        cursorParam: null,
        metaPath: 'response',
        itemsPath: 'response.result',
        offsetBased: false,
        metaResolver: ProviderMetaResolver::class,
    ),
);
```

Итог: все paginated‑запросы используют `page/rows`, `response` и `response.result` без повторения атрибута.

### 2) Meta‑resolver на уровне клиента
```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\PaginationConfig;
use App\Sdk\Pagination\ProviderMetaResolver;

$config = new ClientConfig(
    baseUrl: 'https://api.example.com',
    paginationConfig: new PaginationConfig(
        pageParam: 'page',
        limitParam: 'rows',
        cursorParam: null,
        metaPath: 'response',
        itemsPath: 'response.result',
        offsetBased: false,
        metaResolver: ProviderMetaResolver::class,
    ),
);
```

Итог: `extractMeta()` не нужен в запросах — meta извлекается единообразно через resolver.

### 3) Точечный override через атрибут
```php
use Brahmic\ApiSutra\AbstractPaginatedRequest;
use Brahmic\ApiSutra\Attributes\Pagination;
use App\Sdk\Pagination\SpecialMetaResolver;

#[Pagination(
    itemsPath: 'data.items',
    metaResolver: SpecialMetaResolver::class,
)]
final class SpecialRequest extends AbstractPaginatedRequest
{
}
```

Итог: конкретный запрос переопределяет itemsPath и resolver, не влияя на остальных.

### 4) Максимальный приоритет через метод запроса
```php
use Brahmic\ApiSutra\AbstractPaginatedRequest;
use Brahmic\ApiSutra\VO\PaginationMeta;
use Brahmic\ApiSutra\VO\PipelineContext;
use Brahmic\ApiSutra\Contracts\Pagination\PaginationMetaOverrideInterface;

final class CustomMetaRequest extends AbstractPaginatedRequest implements PaginationMetaOverrideInterface
{
    public function resolvePaginationMeta(
        array $response,
        array $meta,
        PaginationConfig $config,
        ?PipelineContext $context
    ): PaginationMeta
    {
        return new PaginationMeta(
            total: (int) ($response['total'] ?? 0),
            currentPage: (int) ($response['page'] ?? 1),
            perPage: (int) ($response['limit'] ?? 0),
            hasMore: (bool) ($response['has_more'] ?? false),
            nextCursor: $response['next_cursor'] ?? null,
        );
    }
}
```

Итог: метод запроса переопределяет всё остальное и даёт полный контроль над meta‑логикой.

## Риски и нюансы
- Изменение `#[Pagination]` на nullable‑поля требует аккуратной адаптации всех мест, где она читается.
- Неправильное слияние defaults может привести к неверному `itemsPath`/`metaPath`.
- Нужна явная проверка корректности `metaResolver` (class‑string/инстанс).

## Отложено
- Возврат к тестовому провайдеру (Provider C) после рефакторинга ядра.
