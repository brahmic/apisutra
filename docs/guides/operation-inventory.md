# Operation Inventory

Read-only inventory of provider SDK operations, built from request introspection.

## Зачем это нужно
Этот слой нужен, когда SDK должен уметь:
- получить список всех декларативно описанных операций;
- отдать method/endpoint/responseType по request-классам;
- отдать стабильные SDK call paths вида `v1()->objects()->getByCadnum()`;
- собрать единый DX snapshot без ручного агрегатора в каждом provider package;
- использовать request metadata в tooling, docs, admin UI и внутренних справочниках.

Если provider SDK начинает вручную писать код для:
- scan request classes
- resolve `RequestSpec`
- normalize operation metadata
- filter by request/endpoint
- восстанавливать `resource()->method()` через локальную reflection-логику

это хороший сигнал использовать `OperationInventory`.

## Что это такое
`OperationInventory` строится на основе:
- `RequestScanner`
- `RequestSpecResolver`
- `OperationDescriptor`

Это не runtime слой:
- не использует `ExecutionResult`
- не зависит от transport/pipeline
- не делает I/O

Это не provider catalog:
- inventory — introspection layer поверх request-классов
- catalogs — отдельный aggregated static knowledge layer SDK

## Базовый API
- `OperationInventoryInterface`
- `OperationDescriptorView`
- `OperationInventoryBuilder`

Минимальный `OperationDescriptorView`:
- `requestClass`
- `httpMethod`
- `endpoint`
- `responseType`
- `operationDescriptor`
- `hasDownload`
- `hasNoAuth`
- `skipCredentialsEnrichment`
- `sdkCallPaths`
- `continuationFinalType` — финальный DTO для async-цепочки (`#[ContinuationResult(finalType: ...)]`)
- `continuationUnwrap` — путь к финальному payload внутри envelope для await
- `pollRequestClass` — класс poll-запроса (если задан в `ContinuationResult`)
- `returnsUnwrap` — путь к sync payload внутри envelope (`Returns::unwrap`)
- `resourcePath` / `resourceLabel` — иерархия ресурсов (см. `ResourceNameResolverInterface`)

Convenience accessors:
- `title()`
- `description()`
- `note()`
- `isAsync()` — true, если задан `#[ContinuationResult]`

## Доступ
На уровне клиента:
- `$client->operationInventory()`

Из inventory:
- `all()`
- `forRequest(FQCN)`
- `forEndpoint('/path')`

## Пример
```php
$inventory = $client->operationInventory();

$all = $inventory->all();
$byRequest = $inventory->forRequest(GetHistoryRequest::class);
$byEndpoint = $inventory->forEndpoint('/rights/history');
$paths = $byRequest?->sdkCallPaths ?? [];
```

Если request-класс имеет:
```php
#[OperationDescriptor(
    title: 'Get history',
    description: 'Returns rights history for the object.',
    note: 'Paid operation.',
)]
```

то inventory отдаст эти данные через `operationDescriptor`, `title()`,
`description()` и `note()`.

Для SDK call paths:
- `sdkCallPaths` — это список стабильных публичных entrypoint-путей от корня клиента;
- формат строки: `resource()->method()` или `v1()->resource()->method()`;
- список dedupe-нут и отсортирован лексикографически;
- порядок не означает “primary path” против “alias path”.

Пример:
```php
$operation = $client->operationInventory()->forRequest(GetByCadnumRequest::class);

// [
//     'objects()->getByCadnum()',
//     'v1()->objects()->getByCadnum()',
// ]
$paths = $operation?->sdkCallPaths ?? [];
```

## Как строятся sdkCallPaths
`sdkCallPaths` резолвятся только при сборке inventory для конкретного клиента:
- через `$client->operationInventory()`;
- через `OperationInventoryBuilder::buildForClient(...)`.

Для `OperationInventoryBuilder::buildForRootNamespace(...)` поле всегда будет пустым:
- `sdkCallPaths: []`

Причина простая: без concrete client class introspection-слой не может
контрактно доказать стабильный SDK entrypoint path.

## Поддерживаемые паттерны
- публичные client/resource/router методы с declared return type;
- цепочки `client -> resource -> request`;
- alias-методы, если они сами публичны и возвращают тот же request-класс;
- version shortcut methods вида `v1()`, `v2()`, `v3()`;
- version-aware routing через `requestByVersion([...])` и `resourceByVersion([...])`,
  когда путь можно статически сузить до конкретного request-класса.

## Что не поддерживается
- parameterized selectors вида `useVersion(...)` как stable path;
- магия через `__call`;
- методы без declared return type;
- runtime-only построение цепочек;
- методы, которые возвращают `ResultHandle`, `ResolvedResultInterface`, DTO,
  scalar и другие не-request entrypoints.

## Важные ограничения
- inventory использует **declarative request spec**, а не runtime override
- если request меняет endpoint через `resolveEndpoint()`, inventory показывает
  именно декларативный endpoint из атрибутов
- `sdkCallPaths` тоже относятся к declarative introspection и не отражают runtime override
- если version-dependent метод нельзя сузить до одного request-класса без runtime контекста,
  path не экспортируется
- inventory не заменяет provider catalogs
- inventory не строит resource tree как first-class модель

## Когда использовать inventory, а когда catalog
Используйте `OperationInventory`, когда нужен:
- универсальный снимок всех request-операций SDK
- introspection по method/endpoint/request class
- стабильный список SDK call paths для request-класса
- DX/tooling слой поверх request-классов

Используйте `ProviderCatalog*`, когда нужен:
- pricing catalog
- capability catalog
- operation descriptor catalog как самостоятельный static dataset
- request-bound или domain-bound knowledge layer, не сводимая к одному `RequestSpec`

## ResponseDtoCatalog
Каталог response DTO — тонкий introspection-слой поверх `OperationInventory`,
отвечающий на вопрос «какие DTO реально возвращает SDK».

### Что входит в каталог
- sync DTO из `#[Returns(...)]` или `#[Returns(type: ...)]`
- async-final DTO из `#[ContinuationResult(finalType: ...)]`
- download responses (`FileResponse`) из request-ов с `#[Download]`

Если у `Returns` задан `type`, то для каталога приоритет именно за `type`.
Если у request нет ни `Returns`, ни `ContinuationResult`, ни `#[Download]`,
он в каталог не попадает — но остаётся виден в обычном `OperationInventory`.

### Доступ
На уровне клиента:
```php
$catalog = $client->responseDtoCatalog();
```

Или вручную:
```php
use Brahmic\ApiSutra\OperationInventory\Catalog\ResponseDtoCatalog;

$catalog = new ResponseDtoCatalog($client->operationInventory());
```

### API
- `listAllDtoClasses(includeDownload = false): array<int, class-string>`
- `listSyncDtoClasses(): array<int, class-string>`
- `listAsyncFinalDtoClasses(): array<int, class-string>`
- `listDownloadResponseClasses(): array<int, class-string>`
- `usages(): array<class-string, array<int, ResponseDtoUsage>>`
- `usagesFor(class-string): array<int, ResponseDtoUsage>`
- `inventory(): OperationInventoryInterface`

`Download` намеренно не входит в `listAllDtoClasses()` по умолчанию, чтобы DTO и
`FileResponse` не смешивались. Включается явным `includeDownload: true` или через
`listDownloadResponseClasses()`.

### `ResponseDtoUsage`
Каждая запись каталога — это `ResponseDtoUsage` со всеми полями, нужными exporter-ам:

- `requestClass`
- `resourcePath: array<int, string>|null`
- `resourceLabel: string|null`
- `httpMethod: HttpMethod|null`
- `endpoint: string|null`
- `title: string|null` / `description: string|null` (из `OperationDescriptor`)
- `responseClass: class-string` (sync DTO / async final DTO / `FileResponse`)
- `kind: ResponseDtoKind` — `Sync` / `AsyncFinal` / `Download`
- `pollRequest: class-string|null` (только для `AsyncFinal`)
- `unwrap: string|null` (`Returns::unwrap` или `ContinuationResult::unwrap`)

Все необходимые данные хранятся прямо в `ResponseDtoUsage` — exporter-у не нужно
делать join с `OperationInventory`.

### Resource grouping
Resource — естественная top-level группа SDK, и в реальных провайдерах ресурсы
часто **вложены**:

```
\Resources\Reports\Tasks\Requests\CreateByFio\CreateByFioRequest
```

Здесь не один сегмент `Reports`, а путь `['Reports', 'Tasks']`.

Резолвер — `ResourceNameResolverInterface`:
- дефолтная реализация (`ResourceNameResolver`) использует path-эвристику между
  сегментами `\Resources\` и `\Requests\`
- провайдер может зарегистрировать свой resolver при сборке `OperationInventoryBuilder`
- если путь определить нельзя, оба поля (`resourcePath`, `resourceLabel`) будут `null`

`OperationInventory::all()` остаётся flat: каждый элемент хранит свой `resourcePath`
/ `resourceLabel`, иерархия — это уже вид (рендер), а не данные.

### Экспорт в файл
Каталог сам про форматирование ничего не знает. Экспорт инкапсулирован отдельным
слоем по принципу strategy/adapter — новые форматы добавляются без правки самого
каталога.

Контракт:
```php
interface ResponseDtoCatalogExporterInterface
{
    public function format(): string;        // 'md', 'json', 'openapi', ...
    public function export(ResponseDtoCatalog $catalog): string;
}
```

Built-in: `MarkdownResponseDtoCatalogExporter` (format: `md`).

Запись:
```php
use Brahmic\ApiSutra\OperationInventory\Catalog\Export\MarkdownResponseDtoCatalogExporter;
use Brahmic\ApiSutra\OperationInventory\Catalog\Export\ResponseDtoCatalogWriter;

$writer = new ResponseDtoCatalogWriter([
    new MarkdownResponseDtoCatalogExporter(),
]);

$writer->writeTo('/path/dto-catalog.md', $client->responseDtoCatalog());
```

Поведение `writeTo`:
- если `format` не указан, он определяется по расширению пути
- если расширения нет и `format` не указан — `ConfigurationException`
- если для format нет зарегистрированного exporter-а — `ConfigurationException`

Кастомный формат от SDK:
```php
final readonly class JsonResponseDtoCatalogExporter implements ResponseDtoCatalogExporterInterface
{
    public function format(): string { return 'json'; }

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

### Async + Sync на одном request
Если у запроса есть и `Returns(EnvelopeDto::class)`, и `ContinuationResult(finalType: FinalDto::class)`:

- `EnvelopeDto::class` идёт как `Sync`
- `FinalDto::class` идёт как `AsyncFinal`
- оба попадают в `listAllDtoClasses()`
- в `usages()` для каждого DTO — отдельный `ResponseDtoUsage`

### Поллинг-запрос
Poll-запрос — это самостоятельный request-класс со своим `Returns` и попадает в
inventory обычным проходом. В каталоге он:

- виден через свои собственные `Returns`-DTO (как Sync usage)
- дополнительно указывается в `pollRequest` у `AsyncFinal`-usage стартового запроса

### Унифицированный контракт `ResponseDtoCatalogProviderInterface`
Любой провайдер каталога — одиночный клиент или мегаклиент — реализует один
контракт:

```php
interface ResponseDtoCatalogProviderInterface
{
    public function responseDtoCatalog(): ResponseDtoCatalog;
}
```

Имплементируется:

- `AbstractClient` — single-client каталог (`serviceClass` у usages = `null`)
- мегаклиент через `ProvidesMultiServiceResponseDtoCatalogTrait` —
  multi-service каталог (`serviceClass` проставлен на каждый usage)

Это даёт **унифицированный полиморфный обход** разнородных провайдеров:

```php
/** @var array<int, ResponseDtoCatalogProviderInterface> $providers */
$providers = [$realtyClient, $kontur, $tax];

foreach ($providers as $provider) {
    foreach ($provider->responseDtoCatalog()->usages() as $dtoClass => $usages) {
        // ...
    }
}
```

Если нужен **один общий каталог** на произвольный набор провайдеров (а не
итерация по каталогам):

```php
$catalog = (new MultiServiceResponseDtoCatalogFactory())->merge(
    $realtyClient,   // одиночный AbstractClient
    $kontur,         // мегаклиент
    $tax,            // ещё мегаклиент
);
```

`merge()` сам разворачивает мегаклиенты в `services()` и проставляет
`serviceClass` для каждого usage.

### Multi-service usage (мегаклиент)
Если ваш SDK реализует `MultiServiceClientInterface` (см.
[мегаклиент](./megaclient.md)), `ResponseDtoCatalog` поддерживает работу по
всему фасаду:

- `MultiServiceResponseDtoCatalogFactory::fromMultiService($mega)` собирает
  каталог по всем сервисам через `CompositeOperationInventory`
- каждый `ResponseDtoUsage` получает `serviceClass` (FQCN сервис-клиента,
  например `RealtyClient::class`)
- single-service сценарий не меняется: `serviceClass` остаётся `null`, layout
  markdown-exporter полностью идентичен предыдущему

`CompositeOperationInventory` — тонкий агрегатор поверх готовых per-service
inventory, не делает повторного сканирования request-классов:

- `all()` — конкатенация в порядке `services()`
- `forRequest()` — первое совпадение (детерминированно по порядку сервисов)
- `forEndpoint()` — конкатенация совпадений из всех inventories

DX-шорткат на мегаклиенте через `ProvidesMultiServiceResponseDtoCatalogTrait`:
```php
final class MegaClient implements MultiServiceClientInterface
{
    use ProvidesMultiServiceResponseDtoCatalogTrait;
    public function services(): array { /* ... */ }
}

$catalog = $mega->responseDtoCatalog();
```

Полный пример экспорта и кастомного service-label — в гайде
[мегаклиент → Каталог response DTO](./megaclient.md#каталог-response-dto-по-мегаклиенту).
