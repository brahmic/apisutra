# План: сервис-хелпер для перечня response DTO

## Проблема и ожидания
Сейчас в `apisutra` есть `OperationInventory`, который умеет отдавать список всех request-операций SDK
с метаданными:

- `requestClass`
- `httpMethod`
- `endpoint`
- `responseType`
- `operationDescriptor`
- `hasDownload`, `hasNoAuth`, `skipCredentialsEnrichment`
- `sdkCallPaths`

Этого достаточно для introspection request-классов, но **недостаточно** для одного важного DX-кейса:

> "дай мне перечень всех DTO, которые возвращают запросы SDK"

Сейчас:
- `responseType` отдаётся только для sync-запросов (через `#[Returns(...)]`)
- async-final DTO (`#[ContinuationResult(finalType: ...)]`) **не отображается** в `OperationDescriptorView`

То есть текущий inventory **не покрывает async case** наружу, хотя сам `RequestSpec` уже хранит
`continuationResult`.

### Что хотим получить
Шорт-лист задач:

1. Полный перечень всех response DTO, которые SDK реально возвращает (sync + async).
2. Учёт асинхронных запросов:
   - первый запрос возвращает start envelope DTO (`Returns`)
   - финальный DTO определяется через `ContinuationResult::finalType`
3. Удобный сервис-хелпер поверх существующего `OperationInventory`.
4. Без дублирования логики `RequestSpecResolver` / `RequestScanner`.
5. Без размытия ответственности `OperationInventory` (он остаётся introspection-слоем).

---

## Главная цель
Добавить в `apisutra` thin-сервис, который:

- использует существующий `OperationInventory`
- понимает sync и async response DTO
- отдаёт удобные срезы:
  - все unique response DTO classes
  - usages: какой DTO в каком request-классе и в каком kind (`sync` / `async`)
- ничего не ломает в existing API

И минимально расширить `OperationDescriptorView`, чтобы async-метаданные стали доступны наружу.

---

## Границы рефакторинга

## В scope
- `OperationInventory/OperationDescriptorView`
- `OperationInventory/OperationInventoryBuilder`
- новый сервис `ResponseDtoCatalog` (рабочее имя)
- слой экспорта в файл (markdown как built-in, остальные форматы — через адаптеры)
- тесты на sync + async кейсы
- тесты на экспорт
- docs:
  - `docs/guides/operation-inventory.md`
  - `docs/guides/provider-async-await.md` (точечно)

## Вне scope
- `RequestSpecResolver` (уже отдаёт всё нужное)
- `Hydrator` / `DtoSerializer`
- `ContinuationService`
- transport / wire layer
- любые runtime изменения pipeline

То есть это **только static introspection layer** поверх уже собранных метаданных.

---

## Что есть сейчас и что не хватает

## Уже есть
- `RequestSpec::responseType`
- `RequestSpec::continuationResult` (`ContinuationResult` объект)
- `OperationInventoryBuilder` строит `OperationDescriptorView` для всех request-классов
- `OperationDescriptorView` отдаёт `responseType`

## Чего не хватает
- `OperationDescriptorView` не отдаёт:
  - `continuationFinalType`
  - `continuationUnwrap`
  - `pollRequestClass`
- нет публичного хелпера, который собрал бы перечень response DTO

---

## Целевой API

## 1. Расширение `OperationDescriptorView`
Добавить поля:

- `?string $continuationFinalType`
- `?string $continuationUnwrap`
- `?string $pollRequestClass`

И опциональный helper:
- `bool isAsync(): bool` — true, если есть `ContinuationResult`

## 2. Новый сервис `ResponseDtoCatalog`
Файл (рабочее имя):
- `src/OperationInventory/ResponseDtoCatalog.php`

### Контракт
```php
final readonly class ResponseDtoCatalog
{
    public function __construct(
        private OperationInventoryInterface $inventory,
    ) {}

    /**
     * @return array<int, class-string>
     */
    public function listAllDtoClasses(bool $includeDownload = false): array;

    /**
     * @return array<int, class-string>
     */
    public function listSyncDtoClasses(): array;

    /**
     * @return array<int, class-string>
     */
    public function listAsyncFinalDtoClasses(): array;

    /**
     * @return array<int, class-string>
     */
    public function listDownloadResponseClasses(): array;

    /**
     * @return array<class-string, array<int, ResponseDtoUsage>>
     */
    public function usages(): array;
}
```

И вспомогательный VO:
```php
final readonly class ResponseDtoUsage
{
    /**
     * @param class-string                $requestClass
     * @param array<int, string>|null     $resourcePath
     * @param class-string                $responseClass
     * @param class-string|null           $pollRequest
     */
    public function __construct(
        public string $requestClass,
        public ?array $resourcePath,
        public ?string $resourceLabel,
        public ?HttpMethod $httpMethod,
        public ?string $endpoint,
        public ?string $title,
        public ?string $description,
        public string $responseClass,
        public ResponseDtoKind $kind,
        public ?string $pollRequest = null,
        public ?string $unwrap = null,
    ) {}
}
```

Все поля специально хранятся прямо в `ResponseDtoUsage`, чтобы:

- exporter-ы (markdown / json / openapi) получали всю информацию из `usages()` одним
  проходом, без join-ов с `OperationInventory`
- DX-кейс "дай мне всё про этот DTO одним объектом" закрывался без обходных путей
- сохранялся единый источник истины: один проход по inventory → готовые `ResponseDtoUsage`

Семантика полей:
- `requestClass` — request-класс, который реально возвращает этот DTO
- `resourcePath` / `resourceLabel` — иерархия ресурсов (см. `ResourceNameResolverInterface`)
- `httpMethod` / `endpoint` — транспортные координаты операции
- `title` / `description` — берутся из `OperationDescriptor`, если задан
- `responseClass` — конкретный response-class для этого usage
  (sync DTO / async final DTO / `FileResponse` для download)
- `kind` — какой тип ответа описывает этот usage
- `pollRequest` — для async-final: класс poll-запроса (если задан в `ContinuationResult`)
- `unwrap` — поле unwrap из `Returns`/`ContinuationResult`, если задано

И enum:
```php
enum ResponseDtoKind: string
{
    case Sync = 'sync';
    case AsyncFinal = 'async_final';
    case Download = 'download';
}
```

`Download` нужен, чтобы не потерять информацию о response shape для download-запросов (см. ниже).

## 3. Сервис должен:
- ходить по `$inventory->all()`
- для каждого `OperationDescriptorView` собирать sync `responseType`
- собирать async `continuationFinalType`
- складывать в дедуплицированный list

## 4. Точка доступа
Можно дать удобный shortcut на клиенте:

```php
$client->responseDtoCatalog(): ResponseDtoCatalog
```

Это симметрично уже существующему `$client->operationInventory()`.

## 5. Слой экспорта в файл
Каталог сам **не знает про форматирование**. Экспорт инкапсулирован отдельным слоем по принципу
strategy/adapter, чтобы новые форматы добавлялись без правки самого каталога.

### Контракт
```php
interface ResponseDtoCatalogExporterInterface
{
    public function format(): string;        // 'md', 'json', 'openapi', ...
    public function export(ResponseDtoCatalog $catalog): string;
}
```

### Built-in exporter
В ядре `apisutra` сразу поставляется один:

- `MarkdownResponseDtoCatalogExporter` (format: `md`)

Всё остальное (`json`, `openapi`, `html` и т.п.) — это уже либо отдельные пакеты, либо custom
exporter в SDK провайдера.

### Сервис записи
```php
final readonly class ResponseDtoCatalogWriter
{
    /**
     * @param array<int, ResponseDtoCatalogExporterInterface> $exporters
     */
    public function __construct(
        private array $exporters,
    ) {}

    public function writeTo(
        string $path,
        ResponseDtoCatalog $catalog,
        ?string $format = null,
    ): void;

    public function render(
        ResponseDtoCatalog $catalog,
        string $format,
    ): string;
}
```

### Поведение
- если `$format` не указан, он определяется по расширению `$path`
- если расширения нет и `$format` не указан — `ConfigurationException`
- если для формата нет зарегистрированного exporter — `ConfigurationException`

### Почему именно так
- каталог остаётся чистым data provider
- никаких `toMarkdown()`, `toJson()`, `toOpenApi()` методов в каталоге
- любой SDK / провайдерный пакет может зарегистрировать свой exporter
- каждый exporter тестируется отдельно
- `writer` остаётся тонким facade

### Точка доступа
Опционально дать short-path на клиенте:

```php
$client->responseDtoCatalog()->writeTo('/path/dto-catalog.md');
```

или явно через `writer`:

```php
$writer = new ResponseDtoCatalogWriter([
    new MarkdownResponseDtoCatalogExporter(),
]);

$writer->writeTo('/path/dto-catalog.md', $client->responseDtoCatalog());
```

---

## Поведенческие правила

### Resource grouping
Resource — естественная top-level группа SDK, но в реальных SDK resource часто **вложен**:

```
\Resources\Reports\Tasks\Requests\CreateByFio\CreateByFioRequest
```

Здесь не одно значение `Reports`, а path `['Reports', 'Tasks']`.

#### В данных
- хранится как `resourcePath: array<int, string>`
- дополнительно отдаётся удобный `resourceLabel: string`, например `'Reports / Tasks'`
- если вычислить путь не удаётся — оба поля `null`

#### Эвристика по умолчанию
- найти сегменты между `\Resources\` и `\Requests\`
- эти сегменты и есть `resourcePath`

Примеры:
- `\Resources\Reports\Tasks\Requests\CreateByFioRequest` → `['Reports', 'Tasks']`
- `\Resources\Files\Requests\DownloadAttachmentRequest` → `['Files']`

#### Override
Эвристика должна быть **переопределяемой** провайдером, чтобы не навязывать структуру:

- интерфейс `ResourceNameResolverInterface`
- default implementation использует path-эвристику
- провайдер может зарегистрировать свой resolver

#### Где появляется
Resource попадает:
- в `OperationDescriptorView` как пара полей:
  - `?array $resourcePath`
  - `?string $resourceLabel`
- в markdown layout как **иерархическая** группировка по top-level resource и sub-resource

#### В плоском списке
`OperationInventory::all()` остаётся flat: каждый элемент хранит свой `resourcePath`/`resourceLabel`,
вложенность там не строится. Иерархия — это уже вид (рендер), а не данные.

### Группировка vs данные
Чёткое правило:
- `ResponseDtoCatalog` отдаёт **flat data**:
  - lists of class-strings
  - usages by DTO
- любая группировка (by-resource, by-kind, by-DTO) делается **в exporter-ах**
- если группировки понадобятся centralized, отдельно вводится `ResponseDtoCatalogReport` VO,
  но **коллекции для этого не плодим** — это derived view, а не модель данных

### Async + Sync
Если у запроса есть и `Returns(EnvelopeDto::class)`, и `ContinuationResult(finalType: FinalDto::class)`, то:

- `EnvelopeDto::class` идёт как `sync`
- `FinalDto::class` идёт как `async_final`
- оба должны попасть в `listAllDtoClasses()`

### `pollRequest`
Поллинг-запрос — это самостоятельный request-класс со своим `Returns`.

Он автоматически попадает в каталог через обычный inventory pass.

Не дублировать его как `async_final`, если `ContinuationResult` указывает на другой DTO.

### `Returns(unwrap: ..., type: ...)`
Если у `Returns` есть `type`, то для каталога подходит:
- предпочитать `type` если он задан
- иначе `response`

### Отсутствие `Returns`
Если у запроса нет `Returns`:
- такой request не учитывать в `listSyncDtoClasses()`
- но он может всё ещё иметь `ContinuationResult` (тогда учитывается только async-final)

### Download requests
Если `hasDownload === true`:
- response — это не DTO, а `FileResponse` (или эквивалент)
- информация о таком response **не должна теряться**

Поведение каталога:
- такие request-ы попадают в `usages()` с `kind = ResponseDtoKind::Download`
- response class указывается как класс file-response сущности (например, `FileResponse::class`)
- по умолчанию они **не входят** в `listAllDtoClasses()` / `listSyncDtoClasses()`,
  чтобы не смешивать DTO и file-response в одном списке без явного opt-in
- доступ к ним через явные методы:
  - `listDownloadResponseClasses(): array<int, class-string>`
  - `listAllDtoClasses(includeDownload: bool = false): array<int, class-string>`

То есть данные сохраняются, но представление честно разделено по типам ответа.

---

## Что менять в коде

## 1. `OperationDescriptorView`
- добавить поля:
  - `?string $continuationFinalType`
  - `?string $continuationUnwrap`
  - `?string $pollRequestClass`
- добавить helper `isAsync()`

## 2. `OperationInventoryBuilder::buildInventory(...)`
- читать `$spec->continuationResult`
- пробрасывать поля в `OperationDescriptorView`

## 3. Новые файлы каталога
- `src/Enums/Inventory/ResponseDtoKind.php`
- `src/OperationInventory/Catalog/ResponseDtoUsage.php`
- `src/OperationInventory/Catalog/ResponseDtoCatalog.php`

## 4. Новые файлы экспорта
- `src/Contracts/Interfaces/Inventory/ResponseDtoCatalogExporterInterface.php`
- `src/OperationInventory/Catalog/Export/ResponseDtoCatalogWriter.php`
- `src/OperationInventory/Catalog/Export/MarkdownResponseDtoCatalogExporter.php`

### Структура и неймспейсы
Чтобы `OperationInventory/` не превратился в свалку, новые вещи укладываются строго так:

```
src/
    Contracts/Interfaces/Inventory/
        ResponseDtoCatalogExporterInterface.php

    Enums/Inventory/
        ResponseDtoKind.php

    OperationInventory/
        OperationInventory.php
        OperationInventoryBuilder.php
        OperationDescriptorView.php
        SdkCallPathResolver.php
        SdkCallPathMethodAnalyzer.php

        Catalog/
            ResponseDtoCatalog.php
            ResponseDtoUsage.php

            Export/
                ResponseDtoCatalogWriter.php
                MarkdownResponseDtoCatalogExporter.php
```

Принципы:
- `OperationInventory/` остаётся "build-метаданных по запросам"
- `OperationInventory/Catalog/` — собственно DTO catalog слой
- `OperationInventory/Catalog/Export/` — экспортёры и writer
- контракты живут в `Contracts/Interfaces/Inventory/`
- enums — в `Enums/Inventory/`

## 5. `AbstractClient`
- добавить shortcut:
  - `public function responseDtoCatalog(): ResponseDtoCatalog`
- кешировать как и `operationInventory()`
- (опционально) при наличии built-in markdown exporter дать удобный shortcut на запись

---

## Тестовый план

## 1. Sync only
Запрос с `#[Returns(UserDto::class)]`:
- `listSyncDtoClasses()` содержит `UserDto`
- `listAsyncFinalDtoClasses()` пустой

## 2. Async only
Запрос с `#[ContinuationResult(finalType: ReportDto::class)]`:
- `listSyncDtoClasses()` пустой
- `listAsyncFinalDtoClasses()` содержит `ReportDto`

## 3. Sync + async
Запрос с обоими атрибутами:
- оба DTO попадают в `listAllDtoClasses()`
- usages содержит оба `ResponseDtoUsage`

## 4. `Returns(type: ...)`
- `type` имеет приоритет

## 5. `pollRequest` отдельный
Если `ContinuationResult::pollRequest` указан:
- сам poll request тоже сканируется и попадает в inventory как обычный request
- для него тоже считается DTO

## 6. Дедупликация
Если несколько request-классов возвращают один и тот же DTO:
- `listAllDtoClasses()` отдаёт его один раз
- `usages()` отдаёт все request-классы

## 7. Download
Запрос с `hasDownload`:
- по умолчанию не попадает в `listAllDtoClasses()` / `listSyncDtoClasses()`
- попадает в `listDownloadResponseClasses()`
- попадает в `usages()` с `kind = ResponseDtoKind::Download`
- его response class указан как `FileResponse::class` (или эквивалент)

## 8. Регрессии
- existing `OperationInventory` API не ломается
- existing fields `OperationDescriptorView` остаются на месте

## 9. Markdown export
- exporter генерирует читаемый markdown
- содержит секции:
  - header + summary (счётчики по kind, имя клиента)
  - TL;DR table: DTO × kind
  - by-resource sections с поддержкой **вложенных ресурсов** (top-level + sub-resource по `resourcePath`); каждая операция со своим title/description, HTTP, sync/async/download response
  - by-DTO usages: для каждого DTO список request-классов + kind
- стабильный порядок (по resource, потом по request-class)
- async раскрывается так:
  - sync response: envelope DTO
  - async final: финальный DTO
  - poll request: класс poll-запроса
- download отдельной секцией, response class указывается явно
- title/description берётся из `OperationDescriptor`, если задан
- если `OperationDescriptor` отсутствует, выводится только requestClass

## 10. Writer
- определение format по расширению пути
- ошибка при отсутствии exporter для format
- успешная запись на диск возвращает path / void
- read-after-write проверка содержимого

## 11. Custom exporter
- registration custom exporter
- writer корректно подбирает custom format
- встроенный markdown не перебивается, если SDK добавил свой

---

## Документация
Обновить:
- `docs/guides/operation-inventory.md` — основной guide
- `docs/glossary/architecture.md` — короткое определение `ResponseDtoCatalog`
- `docs/guides/provider-async-await.md` — упомянуть, что async-final тоже виден в каталоге

---

## Риски

## Риск 1. Размытие ответственности `OperationInventory`
### Почему
Появляется новый concept "DTO catalog".

### Меры
- держать `ResponseDtoCatalog` как отдельный сервис
- не пихать его методы внутрь `OperationInventory`
- inventory остаётся introspection-слоем по request-классам

## Риск 2. Дублирование с уже завершёнными ProviderCatalogs
### Почему
По названию `Catalog` может прозвучать похоже.

### Меры
- явно зафиксировать в docs:
  - `ProviderCatalogs` — read-only static SDK metadata
  - `ResponseDtoCatalog` — derived introspection from inventory
- чтобы не путать слои

## Риск 3. Двусмысленность для async
### Почему
Один request имеет несколько связанных DTO.

### Меры
- отдавать `ResponseDtoUsage` с явным `kind`
- в docs объяснить разницу

## Риск 4. Поведение для `Download`
### Почему
DTO там может вообще не быть.

### Меры
- по умолчанию не включать
- если потребуется, дать opt-in через отдельный метод

---

## DX после реализации

## Прямой usage
```php
$catalog = $client->responseDtoCatalog();

$all = $catalog->listAllDtoClasses();
$sync = $catalog->listSyncDtoClasses();
$async = $catalog->listAsyncFinalDtoClasses();

foreach ($catalog->usages() as $dtoClass => $usages) {
    foreach ($usages as $usage) {
        $usage->requestClass;
        $usage->kind; // ResponseDtoKind::Sync | ResponseDtoKind::AsyncFinal
    }
}
```

## Через inventory напрямую
```php
foreach ($client->operationInventory()->all() as $op) {
    $op->responseType;             // sync DTO
    $op->continuationFinalType;    // async final DTO
    $op->isAsync();
}
```

## Экспорт в файл
Built-in:
```php
$client->responseDtoCatalog()->writeTo('/path/dto-catalog.md');
```

Через writer и явный exporter:
```php
$writer = new ResponseDtoCatalogWriter([
    new MarkdownResponseDtoCatalogExporter(),
]);

$writer->writeTo('/path/dto-catalog.md', $client->responseDtoCatalog());
```

Кастомный формат от SDK:
```php
final readonly class JsonResponseDtoCatalogExporter implements ResponseDtoCatalogExporterInterface
{
    public function format(): string
    {
        return 'json';
    }

    public function export(ResponseDtoCatalog $catalog): string
    {
        return json_encode([
            'all'   => $catalog->listAllDtoClasses(),
            'sync'  => $catalog->listSyncDtoClasses(),
            'async' => $catalog->listAsyncFinalDtoClasses(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}

$writer = new ResponseDtoCatalogWriter([
    new MarkdownResponseDtoCatalogExporter(),
    new JsonResponseDtoCatalogExporter(),
]);

$writer->writeTo('/path/dto-catalog.json', $client->responseDtoCatalog());
```

---

## Готовность к реализации
План готов к реализации.

Почему:
- проблема понятна
- границы узкие
- архитектура однозначна
- риски минимальны
- инфраструктура (`OperationInventory`, `RequestSpec`) уже есть
- задача в основном compositional, не глубокий refactor

Это **малый scoped feature** в `apisutra`, который реально закрывает запрашиваемый кейс.

## Статус реализации
Реализовано полностью.

Что готово:
- `Enums/Inventory/ResponseDtoKind`
- `Contracts/Interfaces/Inventory/ResourceNameResolverInterface` + `OperationInventory/ResourceNameResolver`
- `OperationDescriptorView` расширен (`continuationFinalType`, `continuationUnwrap`,
  `pollRequestClass`, `returnsUnwrap`, `resourcePath`, `resourceLabel`, `isAsync()`)
- `OperationInventoryBuilder` пробрасывает continuation/resource поля и применяет
  resource resolver (можно подменить)
- `OperationInventory/Catalog/ResponseDtoUsage` (полный набор полей)
- `OperationInventory/Catalog/ResponseDtoCatalog` (sync/async/download срезы + usages)
- `Contracts/Interfaces/Inventory/ResponseDtoCatalogExporterInterface`
- `OperationInventory/Catalog/Export/ResponseDtoCatalogWriter`
- `OperationInventory/Catalog/Export/MarkdownResponseDtoCatalogExporter` (built-in)
- `AbstractClient::responseDtoCatalog()` shortcut с кешированием на инстанс
- Тесты:
  - `tests/Unit/OperationInventory/ResourceNameResolverTest.php`
  - `tests/Unit/OperationInventory/OperationDescriptorViewExtensionTest.php`
  - `tests/Unit/OperationInventory/Catalog/ResponseDtoCatalogTest.php`
  - `tests/Unit/OperationInventory/Catalog/Export/ResponseDtoCatalogWriterTest.php`
  - cache-test для `responseDtoCatalog()` shortcut
- Документация:
  - `docs/guides/operation-inventory.md` — расширен раздел `OperationDescriptorView` и
    добавлен раздел `ResponseDtoCatalog` (API, resource grouping, экспорт, async+sync)
  - `docs/guides/provider-async-await.md` — упоминание попадания `finalType` в каталог
  - `docs/glossary/architecture.md` — статьи `OperationInventory` и `ResponseDtoCatalog`
  - `docs/glossary/README.md` — пункты в оглавлении

Тесты apisutra: **691 passed (1663 assertions)**.

