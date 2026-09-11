# Архитектура SDK-пакета

## Терминология мультисервисности

### Provider
SDK‑клиент под конкретное внешнее API вендора. Может включать один
или несколько сервис‑клиентов, если у вендора несколько API‑сервисов.

### Vendor
Поставщик, у которого может быть несколько независимых API‑продуктов
(Realty, Tax и т.д.).

### Service
Логически отдельный клиент с собственными глобальными настройками
(`baseUrl`, `auth`, `pagination`, `serialization`).

### Resource
Группировка запросов внутри одного сервиса (например, entity, drive, rosreestr).

## BaseRequest (per SDK-пакет)
Базовый класс запросов конкретного SDK-пакета. Extends AbstractRequest. Определяет метод resolveClient() который резолвит соответствующий Client через DI-контейнер. Все запросы пакета наследуются от него.

## resolveClient()
Метод в базовом Request SDK-пакета. Возвращает Client через DI-контейнер. Позволяет `(new GetUser(1))->send()` без явной передачи Client. Переопределяется в каждом SDK-пакете.

## Request → Client связь
Архитектурное решение: Request знает свой Client через наследование + DI. Не требуется static resolver, сканирование директорий или явная передача Client. N клиентов в одном приложении изолированы через разные базовые классы.

## RequestSpec
Декларативная часть запроса (атрибуты и метаданные). Кэшируется по классу запроса. Используется для получения метода, endpoint, responseType и конфигурационных атрибутов без рефлексии на каждый вызов.

## RequestOptions
Runtime‑настройки запроса (override‑ы). Иммутабельный VO, модифицируется через `with*` и передаёт в pipeline значения cache/retry/timeout/headers/trace/role и пр.

## PaginationOptions
Runtime‑настройки пагинации (page/limit/cursor). Отдельный иммутабельный VO с флагами `has*`, используется в `RequestExecution` и передаётся в pipeline отдельно от `RequestOptions`.

## RequestExecution
Обёртка над запросом и `RequestOptions`, также содержит `PaginationOptions`. Возвращается из `with*()` и используется для отправки (`send()/sendAsync()`).
Правило DX: кастомные методы запроса вызываются **до** `with*`.

## OperationInventory
Read-only introspection-слой над request-классами SDK. Строится через
`OperationInventoryBuilder` и отдаёт `OperationDescriptorView` по каждому
request-у: `httpMethod`, `endpoint`, `responseType`, `continuationFinalType`,
`pollRequestClass`, `resourcePath`/`resourceLabel`, `sdkCallPaths` и т.п.

## ResponseDtoCatalog
Тонкий derived introspection-слой поверх `OperationInventory`, отвечающий на
вопрос «какие DTO реально возвращает SDK». Агрегирует sync (`Returns`),
async-final (`ContinuationResult::finalType`) и download (`#[Download]` →
`FileResponse`) ответы. Сам про форматирование ничего не знает — экспорт
вынесен в `ResponseDtoCatalogExporterInterface` (built-in Markdown
exporter поставляется из коробки) и `ResponseDtoCatalogWriter`.

## CompositeOperationInventory
Агрегатор поверх нескольких готовых `OperationInventoryInterface`. Используется
для multi-service сценария (мегаклиент): каждый сервис продолжает строить свой
inventory обычным способом, а composite только объединяет их. `forRequest()` —
первое совпадение по порядку, `all()` — конкатенация без пересортировки. Не
делает повторного сканирования request-классов.

## MultiServiceResponseDtoCatalogFactory
Фабрика multi-service `ResponseDtoCatalog`. Собирает inventory со всех
сервис-клиентов мегаклиента, складывает в `CompositeOperationInventory` и
размечает каждый `ResponseDtoUsage` через `MapServiceClassResolver`
(implements `ServiceClassResolverInterface`). Так `serviceClass` становится
частью данных каталога, а не view-слоя.

## ServiceLabelResolverInterface
Презентационный резолвер: даёт человекочитаемый label для сервис-клиента
(`KonturRealtyClient` → `"Realty"`). Используется ТОЛЬКО exporter-ами; в
`ResponseDtoUsage` хранится сырой FQCN. Дефолтная реализация —
`ShortClassServiceLabelResolver`.

## ResponseDtoCatalogProviderInterface
Унифицированный контракт «отдай мне каталог response DTO». Имплементируется
`AbstractClient` и мегаклиентом через `ProvidesMultiServiceResponseDtoCatalogTrait`.
Позволяет SDK единообразно обходить смешанный набор провайдеров и feed-ить их
в `MultiServiceResponseDtoCatalogFactory::merge(...)` для получения сводного
каталога.
