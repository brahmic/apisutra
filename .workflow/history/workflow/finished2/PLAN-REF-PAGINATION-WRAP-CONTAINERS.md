# План: wrap‑пагинация, itemsType и коллекции

## Проблема
Сейчас для paginated‑запросов `ResponseHydrator` вырезает `itemsPath` и возвращает массив items.  
Из‑за этого нельзя получить единый DTO‑контейнер с типизированными items и при этом использовать стандартную пагинацию.

## Цель
Поддержать DX:  
**единый DTO‑контейнер результата + items как коллекция DTO конкретного типа + стандартная пагинация**.

## Рекомендованное решение
1) Убрать `wrapResponse`, разделить ответственность:
   - `itemsPath` используется только для пагинатора (извлечь items),
   - форма результата определяется `#[Returns]`.
2) Добавить `itemsType`, `itemsCollection`, `itemsCollectionFactory`.  
3) Ввести `PaginationItemsContainerInterface` с `items()` и `withItems()`.  
4) Обновить `ResponseHydrator` и `Paginator` под новый режим.

## Основные шаги
1. **Контракты и конфиг**
   - `PaginationItemsContainerInterface`:
     - `items(): array|object`
     - `withItems(array|object $items): static`
   - `PaginationConfig`:
     - `itemsType: ?string`
     - `itemsCollection: ?string`
     - `itemsCollectionFactory: ?string`
   - `#[Pagination]`:
     - `itemsType`, `itemsCollection`, `itemsCollectionFactory` как nullable‑override.

2. **ResponseHydrator**
   - Если `#[Returns]` не задан → возвращается массив items (как сейчас).
   - Если `#[Returns]` задан и запрос `PaginableInterface`:
     - извлечь items по `itemsPath`;
     - гидрировать элементы в `itemsType` (если задан);
     - сформировать коллекцию:
       1) `itemsCollectionFactory` (если задан);
       2) `itemsCollection::fromArray($items)` → `::make($items)` → `new itemsCollection($items)`;
       3) иначе оставить массив.
     - передать items в DTO‑контейнер через `withItems()`.

3. **Paginator**
   - При агрегации:
     - если `data` — массив → как сейчас;
     - если `data` реализует `PaginationItemsContainerInterface` → брать `items()`.
   - При необходимости собирать итоговую коллекцию (если `itemsCollection` задан в config).

4. **Тесты**
   - Без `#[Returns]` — регрессия текущего поведения (items = массив).
   - С `#[Returns]`:
     - DTO‑контейнер + `itemsType` → элементы гидрируются в DTO.
     - `itemsCollection` без фабрики (via `fromArray/make/__construct`).
     - `itemsCollectionFactory` переопределяет способ создания.
     - `Paginator::items()` работает с контейнером.

## Ожидаемый результат (все кейсы/комбинации)
1) **Без `#[Returns]`**  
   - Поведение без изменений: data = массив items, pagination как сейчас.

2) **`#[Returns]` + itemsType**  
   - data = DTO‑контейнер, items внутри — коллекция DTO нужного типа.

3) **`#[Returns]` + itemsType + itemsCollection**  
   - items представлены коллекцией (например, Laravel Collection).

4) **`#[Returns]` + itemsCollectionFactory**  
   - фабрика полностью контролирует создание контейнера коллекции.

5) **`#[Returns]` + без itemsType**  
   - items остаются массивом/коллекцией сырых данных, но контейнер DTO сохраняется.

6) **Override‑логика**
   - `PaginationConfig` задаёт дефолты.
   - `#[Pagination]` частично переопределяет конкретный запрос.
   - Отсутствие параметров не ломает поведение.

## Риски и нюансы
- Нужна чёткая ошибка при невозможности создать коллекцию по `itemsCollection`.
- Важно не ломать существующую пагинацию без wrap‑режима.
- DTO‑контейнер должен быть иммутабелен: `withItems()` возвращает новый экземпляр.

