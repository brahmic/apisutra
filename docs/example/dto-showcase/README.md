# Каталог возможностей DTO

Один вымышленный товар показывает атрибуты, внешний набор правил, вложенные модели,
типизированную коллекцию, варианты элементов, custom cast, provider, extras и ошибки.
Пошаговое объяснение и таблицы результатов — в [обзоре DTO](../../guides/dto/showcase.md).

## Запуск

Из checkout после `composer install`:

```bash
php docs/example/dto-showcase/run.php
```

Из проекта с установленным пакетом:

```bash
php vendor/brahmic/apisutra/docs/example/dto-showcase/run.php
```

Сеть и ключи API не нужны: MockTransport записывает запросы и возвращает фикстуру.
Запуск сравнивает standalone и Returns, отправляет DTO через BodyRoot и собирает
девять ошибок гидратации. [Ожидаемые значения](fixtures/expected.json) заданы отдельно;
проверка документации сверяет их с результатом и контролирует совпадение деклараций в обзоре с кодом.

## Исходники

| Файл | Назначение |
| --- | --- |
| [CatalogItemDto](src/CatalogItemDto.php) | Основная модель с комментариями к каждому приёму |
| [CatalogRules](src/CatalogRules.php) | Strict, списки, each, discriminator и extras |
| [SellerDto](src/SellerDto.php) | Вложенный plain DTO с собственным остатком |
| [TagDto](src/TagDto.php), [TagCollection](src/TagCollection.php) | Типизированная коллекция |
| [ImageDto](src/ImageDto.php), [VideoDto](src/VideoDto.php) | Два варианта media |
| [ItemStatus](src/ItemStatus.php) | Backed enum |
| [MinorUnitsCast](src/MinorUnitsCast.php) | Точное преобразование строки цены в целые сотые и обратно |
| [DisplayNameProvider](src/DisplayNameProvider.php) | Вычисление отсутствующего значения из исходных данных |
| [CatalogClient](src/CatalogClient.php) | Клиент с тем же набором правил |
| [GetCatalogItemRequest](src/GetCatalogItemRequest.php) | Returns с unwrap |
| [SaveCatalogItemRequest](src/SaveCatalogItemRequest.php) | Сериализация DTO в тело запроса |
| [item.json](fixtures/item.json), [expected.json](fixtures/expected.json) | Входные данные и ожидаемое поведение |
| [run.php](run.php) | Сборка, преобразования, сравнение результатов и ошибочные сценарии |

Формат вывода: `dto` — значения и типы через наблюдаемые свойства, `dx` — toArray(),
`wire` — JSON отправленного запроса, `fallback` — запасной ключ и defaults,
`errors` — причины и пути ошибок, `conflictRejected` — отказ при пересечении деклараций.

[Выбрать способ описания DTO](../../start/describe-dto.md) · [Все примеры](../README.md).
