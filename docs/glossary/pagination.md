# Пагинация

## PaginableInterface
Интерфейс для запросов с поддержкой пагинации. Методы: withPage(), withLimit(), withCursor() (опц.) возвращают `RequestExecutionInterface`, extractMeta() извлекает мету из ответа.

## Pagination (атрибут)
Атрибут конфигурации пагинации на запросе. Параметры: pageParam, limitParam, cursorParam, metaPath, itemsPath, offsetBased. Переопределяет defaults из ClientConfig.

## PaginationConfig
Конфиг пагинации на уровне клиента. Дополнительно поддерживает:
itemsType, itemsCollection, itemsCollectionFactory, metaResolver, maxPages.

## PaginationRule
Value Object правил пагинации. Режимы задаются через PaginationMode. Поле failStrategy определяет поведение при ошибках. По умолчанию задаётся в ClientConfig::paginationRule и может быть переопределён через RequestOptions::withPaginationRule() или chain‑метод rules().

## PaginationMode
Enum режима пагинации. Значения: Single (одна страница), All (все страницы), Pages (N страниц), Range (диапазон). Используется в PaginationRule.

## PaginationMeta
Implements ResultMeta. Value Object с метаданными пагинации. Содержит: total, currentPage, perPage, hasMore, nextCursor. Извлекается из ответа API через атрибут metaPath или метод extractMeta().

## PaginationMetaResolverInterface
Контракт для извлечения метаданных пагинации из ответа провайдера.
Можно задать в PaginationConfig или атрибуте Pagination.

## PaginationMetaOverrideInterface
Интерфейс для запросов, которые сами извлекают мету и хотят переопределить стандартный резолв.

## PaginationItemsContainerInterface
Контракт контейнера с items, нужен для DTO‑обёрток пагинированных ответов.

## AbstractPaginationContainerDto
Базовый DTO‑контейнер с items() / withItems() для пагинации.

## Paginator
Сервис для массовой загрузки страниц. Методы: all() — все страницы, pages(n) — первые N, range(from, to) — диапазон. Поддерживает ленивую итерацию через foreach. Мутабельный builder: `perPage()/failStrategy()/range()` меняют внутреннее состояние.

## PaginatedResult
Результат пагинации с aggregated items/pages и метой. Методы: items(), pages(), meta().
