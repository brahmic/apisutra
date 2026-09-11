# План: ResponseDtoCatalog для мегаклиента (multi-service)

## Контекст
`ResponseDtoCatalog` уже реализован и закрывает кейс «дай мне перечень всех DTO,
которые возвращает SDK», но **только в рамках одного сервис-клиента**. В
мультисервисной архитектуре (`MultiServiceClientInterface`, например `KonturClient`
с `RealtyClient` / `TaxClient`) сейчас приходится вручную обходить `services()` и
склеивать каталоги — единого view нет, а сама принадлежность DTO к сервису теряется.

Эта задача — расширение уже сделанного, без переделки текущего поведения.

## Решение в одну фразу
Дать composite-инвентарь поверх `MultiServiceClientInterface`, добавить
опциональный `serviceClass` в `ResponseDtoUsage` и опциональный service-разрез в
markdown-exporter — так, чтобы single-client сценарий остался полностью
неизменным.

---

## Главные принципы
1. Single-client остаётся как есть. `?string $serviceClass = null` по умолчанию,
   exporter секцию по сервисам не рендерит, если нет ни одного non-null значения.
2. Composite не сканирует request-классы заново — только агрегирует уже готовые
   `OperationInventory` сервисов.
3. Service-метка — часть данных, а не вида. Любая группировка/фильтрация по
   сервису возможна на стороне SDK и exporter-ов.
4. `MultiServiceClientInterface` не разрастается. Shortcut добавляется через
   tonкий helper / factory, чтобы интерфейс остался одно-методным.
5. Никаких изменений на стороне SDK для тех, кому мульти-каталог не нужен.

---

## Границы

### В scope
- `OperationInventory/CompositeOperationInventory` (новый)
- `OperationInventory/Catalog/ResponseDtoUsage` (расширение: `?string $serviceClass = null`)
- `OperationInventory/Catalog/ResponseDtoCatalog` (без изменений API; только заполнение `serviceClass` через builder)
- `OperationInventory/Catalog/MultiServiceResponseDtoCatalogFactory` (новый)
- (опционально) `Contracts/Interfaces/Inventory/ServiceLabelResolverInterface` + дефолтная реализация
- `MarkdownResponseDtoCatalogExporter` (опциональная секция by-service, активная только при наличии service-меток)
- тесты на composite + multi-service catalog
- docs:
  - `docs/guides/megaclient.md` — раздел «Каталог response DTO по мегаклиенту»
  - `docs/guides/operation-inventory.md` — раздел «Multi-service usage»
  - `docs/glossary/architecture.md` — упоминание `CompositeOperationInventory` и multi-service catalog

### Вне scope
- сканирование request-классов внутри composite (используем готовые `operationInventory()` сервисов)
- любые изменения `ClientInterface`, `MultiServiceClientInterface`, `AbstractClient` execution path
- любые изменения runtime pipeline / transport / hydrator / serializer
- кеш / фоновые задачи / любые I/O

---

## API

### 1) `CompositeOperationInventory`
Файл: `src/OperationInventory/CompositeOperationInventory.php`

Контракт:
```php
final readonly class CompositeOperationInventory implements OperationInventoryInterface
{
    /**
     * @param array<int, OperationInventoryInterface> $inventories
     */
    public function __construct(private array $inventories) {}

    /** @return array<int, OperationDescriptorView> */
    public function all(): array;

    public function forRequest(string $requestClass): ?OperationDescriptorView;

    /** @return array<int, OperationDescriptorView> */
    public function forEndpoint(string $endpoint): array;
}
```

Поведение:
- `all()` — конкатенация `all()` всех inventories в порядке передачи; **без**
  глобальной пересортировки, чтобы сервис как естественная группа сохранялся
- `forRequest()` — первая встреча в порядке `inventories[]` (детерминированно)
- `forEndpoint()` — конкатенация всех совпадений из всех inventories

Дополнительный helper (необязательный, не часть интерфейса):
- `inventories(): array<int, OperationInventoryInterface>` — для случаев, когда
  caller хочет работать с per-service inventory отдельно

### 2) `ResponseDtoUsage`
Файл: `src/OperationInventory/Catalog/ResponseDtoUsage.php`

Изменение: добавить **в конец** конструктора `?string $serviceClass = null`.

Backward-compat:
- все существующие вызовы продолжат работать
- single-client каталог по-прежнему создаёт usages с `serviceClass = null`

### 3) `MultiServiceResponseDtoCatalogFactory`
Файл: `src/OperationInventory/Catalog/MultiServiceResponseDtoCatalogFactory.php`

Контракт:
```php
final readonly class MultiServiceResponseDtoCatalogFactory
{
    public function __construct(
        private ?ServiceLabelResolverInterface $labelResolver = null,
    ) {}

    public function fromMultiService(MultiServiceClientInterface $mega): ResponseDtoCatalog;

    /**
     * @param array<int, ClientInterface> $services
     */
    public function fromServices(array $services): ResponseDtoCatalog;
}
```

Что делает:
- собирает `operationInventory()` со всех сервисов
- складывает их в `CompositeOperationInventory`
- проставляет `serviceClass` (FQCN сервиса) на каждый usage в каталоге

Нюанс реализации: сейчас `ResponseDtoCatalog` сам строит usages из inventory.
Чтобы не ломать его API, factory:
- либо пробрасывает в `ResponseDtoCatalog` карту `requestClass → serviceClass`
  через дополнительный (опциональный) аргумент конструктора `ResponseDtoCatalog`
- либо постобработкой создаёт новый `ResponseDtoCatalog` с уже размеченными
  `ResponseDtoUsage` через специальный internal-фабричный путь

Решение: добавить **опциональный** второй аргумент конструктора
`ResponseDtoCatalog`:
```php
public function __construct(
    private OperationInventoryInterface $inventory,
    private ?ServiceClassResolverInterface $serviceClassResolver = null,
) {}
```
где `ServiceClassResolverInterface::resolve(string $requestClass): ?string`.
Single-client сценарий остаётся `new ResponseDtoCatalog($inventory)` — без изменений.

Этот контракт internal-friendly, не торчит в публичном DX и не требует от SDK
никаких действий.

### 4) `ServiceLabelResolverInterface` (опционально)
Файл: `src/Contracts/Interfaces/Inventory/ServiceLabelResolverInterface.php`

```php
interface ServiceLabelResolverInterface
{
    /**
     * @param class-string $serviceClass
     */
    public function resolve(string $serviceClass): string;
}
```

Используется **только** exporter-ом для красивых заголовков (например,
`KonturRealtyClient → "Realty"`). По умолчанию — short class name.

Не часть `ResponseDtoUsage`. В каталоге хранится сырой FQCN, label — это
презентационный slug.

### 5) Shortcut на мегаклиенте (без правки interface-а)
Не добавляем метод в `MultiServiceClientInterface` (он остаётся одно-методным —
это было сознательное решение).

Вместо этого даём тонкий trait для удобства:

Файл: `src/Traits/ProvidesMultiServiceResponseDtoCatalogTrait.php`
```php
trait ProvidesMultiServiceResponseDtoCatalogTrait
{
    private ?ResponseDtoCatalog $multiServiceResponseDtoCatalog = null;

    public function responseDtoCatalog(): ResponseDtoCatalog
    {
        if ($this->multiServiceResponseDtoCatalog === null) {
            $this->multiServiceResponseDtoCatalog =
                (new MultiServiceResponseDtoCatalogFactory())->fromMultiService($this);
        }

        return $this->multiServiceResponseDtoCatalog;
    }
}
```

Провайдеру достаточно подмешать trait в свой `MegaClient` и получить
`$mega->responseDtoCatalog()` без бойлерплейта.

Альтернатива (если SDK не хочет tight coupling) — явный вызов фабрики.

### 6) `MarkdownResponseDtoCatalogExporter`
- определяет, есть ли в каталоге хотя бы один `ResponseDtoUsage` с
  `serviceClass !== null`
- если **есть** — добавляет заголовочный разрез: `## Сервисы` с табличкой
  service → counts (sync/async/download), и в by-resource секции добавляет
  префикс `Service:` или вкладывает `### {Service}` → `#### {Resource}`
- если **нет** — рендерит ровно как сейчас (zero-impact для single-client)

Service-label берётся через `ServiceLabelResolverInterface` (default —
short class name).

---

## Поведенческие правила

### Дедупликация DTO между сервисами
- `listAllDtoClasses()` / `listSyncDtoClasses()` / etc. **продолжают**
  возвращать unique class-strings
- если один и тот же FQCN встречается у двух сервисов — он попадёт в список
  один раз, но в `usages()` для него будут `ResponseDtoUsage` от каждого сервиса
  (с разными `serviceClass`)
- это естественно и не требует специальной логики

### Конфликты `forRequest()`
- composite `forRequest()` возвращает первое совпадение (детерминированно по
  порядку `services()`)
- если SDK нужны все совпадения — есть `inventories()` и явный обход

### Resource grouping
- composite **не** объединяет ресурсы между сервисами
- иерархия `Reports / Tasks` в `RealtyClient` и `Reports / Tasks` в `TaxClient`
  остаётся независимой; группировка по resource всегда идёт **внутри** сервиса
  (когда service-разрез активен)

### Download
- `Download` усages так же добавляются с `serviceClass` (если применимо)
- `listAllDtoClasses(includeDownload: true)` работает как раньше

---

## Backward compatibility

| Сценарий                                                | Что меняется |
|---------------------------------------------------------|--------------|
| `new ResponseDtoCatalog($inventory)` (single-client)    | Ничего       |
| `$client->responseDtoCatalog()` (`AbstractClient`)      | Ничего       |
| Существующие тесты single-client                        | Без правок   |
| `OperationInventory::forRequest()`                      | Без изменений|
| `MarkdownResponseDtoCatalogExporter` для single-client  | Идентичный markdown |
| `ResponseDtoUsage` конструктор                          | +1 nullable аргумент в конце, default `null` |
| `MultiServiceClientInterface`                           | Без изменений |

---

## Тестовый план

### `CompositeOperationInventory`
- объединяет `all()` из нескольких inventories в правильном порядке
- `forRequest()` отдаёт первое совпадение
- `forEndpoint()` собирает совпадения из всех inventories
- работает с пустым набором inventories (возвращает пустой `all()`)

### `MultiServiceResponseDtoCatalogFactory`
- собирает каталог из мегаклиента
- проставляет `serviceClass` для каждого usage
- все срезы (`listSyncDtoClasses`, `listAsyncFinalDtoClasses`,
  `listDownloadResponseClasses`) учитывают usages со всех сервисов
- если один и тот же DTO встречается у двух сервисов — он есть в списке один
  раз, но в `usages()` под ним будут записи от обоих сервисов
- при пустом `services()` каталог пустой

### `ResponseDtoCatalog` с `ServiceClassResolverInterface`
- single-client (без resolver-а) — `serviceClass = null`
- multi-service (с resolver-ом) — `serviceClass` корректно проставлен

### `MarkdownResponseDtoCatalogExporter`
- single-client output идентичен текущему (regression)
- multi-service: появляется секция `## Сервисы` и by-service группировка
- service label через `ServiceLabelResolverInterface` подставляется корректно

### Trait
- `responseDtoCatalog()` на мегаклиенте кешируется
- работает поверх `services()` без дополнительных setup-ов

---

## Документация

### Обновить
- `docs/guides/megaclient.md` — новый раздел «Каталог response DTO по мегаклиенту»
  с примерами:
  - `(new MultiServiceResponseDtoCatalogFactory())->fromMultiService($mega)`
  - короткий путь через trait `ProvidesMultiServiceResponseDtoCatalogTrait`
  - явный экспорт в md-файл
- `docs/guides/operation-inventory.md` — раздел «Multi-service usage»:
  - `CompositeOperationInventory`
  - что `forRequest()` детерминирован первым совпадением
  - что `serviceClass` появляется в `ResponseDtoUsage` только в multi-service
    режиме
- `docs/glossary/architecture.md`:
  - короткое определение `CompositeOperationInventory`
  - короткое определение multi-service `ResponseDtoCatalog`
- `docs/glossary/README.md` — пункты в оглавлении

---

## Риски и меры

### Риск 1. Усложнение `ResponseDtoCatalog`
**Почему:** появляется новый optional конструкторный аргумент.
**Мера:** аргумент nullable, single-client путь полностью совместим;
тестируется отдельно (single-client regression suite остаётся как есть).

### Риск 2. Запутанность DX (две точки входа: trait и factory)
**Почему:** есть `ProvidesMultiServiceResponseDtoCatalogTrait` и есть
`MultiServiceResponseDtoCatalogFactory`.
**Мера:** trait — тонкий wrapper над factory, в docs явно указано: factory —
канонический путь, trait — только sugar для тех, кто хочет `$mega->responseDtoCatalog()`.

### Риск 3. Markdown layout разрастётся
**Почему:** появляется новая секция и группировка.
**Мера:** activated only when есть service-метки. Single-client output полностью
совпадает с текущим (verifiable через regression test).

### Риск 4. `forRequest()` коллизии между сервисами
**Почему:** теоретически один `requestClass` может быть в двух сервисах.
**Мера:** документируем «первое совпадение по порядку `services()`», даём
`inventories()` для тех, кому нужно явное разрешение коллизий. На практике
коллизий быть не должно — каждый сервис имеет свой namespace.

---

## DX после реализации

### Через factory
```php
use Brahmic\ApiSutra\OperationInventory\Catalog\MultiServiceResponseDtoCatalogFactory;

$catalog = (new MultiServiceResponseDtoCatalogFactory())->fromMultiService($mega);

$all = $catalog->listAllDtoClasses();

foreach ($catalog->usages() as $dtoClass => $usages) {
    foreach ($usages as $usage) {
        $usage->requestClass;
        $usage->serviceClass;   // FQCN сервис-клиента, например RealtyClient::class
        $usage->resourceLabel;  // 'Reports / Tasks' внутри сервиса
        $usage->kind;
    }
}
```

### Через trait (sugar)
```php
final class KonturClient implements MultiServiceClientInterface
{
    use ProvidesMultiServiceResponseDtoCatalogTrait;
    // ...
}

$catalog = $kontur->responseDtoCatalog();
```

### Экспорт в md
```php
$writer = new ResponseDtoCatalogWriter([
    new MarkdownResponseDtoCatalogExporter(),
]);

$writer->writeTo('/path/dto-catalog.md', $kontur->responseDtoCatalog());
```

В файле появятся секции:
- header + summary
- `## Сервисы` (только если service-метки есть)
- by-service → by-resource операции
- by-DTO usages (с указанием сервисов)
- download responses

---

## Готовность
План готов к ревью. Изменения малые, scope узкий, инфраструктура (composite
поверх готовых inventory + nullable поле в usage) — низкий риск.

После твоего «приступай» начну реализацию строго в рамках `apisutra`. Текущее
состояние: **691 passed (1663 assertions)**.

## Статус реализации
Реализовано полностью.

Что готово:
- `OperationInventory/CompositeOperationInventory` (агрегатор, не сканирует заново)
- `Contracts/Interfaces/Inventory/ServiceClassResolverInterface`
- `Contracts/Interfaces/Inventory/ServiceLabelResolverInterface`
- `OperationInventory/Catalog/MapServiceClassResolver` (внутренний дефолт для factory)
- `OperationInventory/Catalog/ShortClassServiceLabelResolver` (дефолт для exporter)
- `OperationInventory/Catalog/MultiServiceResponseDtoCatalogFactory`
  (`fromMultiService($mega)` / `fromServices(array)`)
- `Traits/ProvidesMultiServiceResponseDtoCatalogTrait` (sugar для мегаклиента)
- `OperationInventory/Catalog/ResponseDtoCatalog` (+ опциональный
  `?ServiceClassResolverInterface` 2-м аргументом, single-client путь без изменений)
- `OperationInventory/Catalog/ResponseDtoUsage` (+ `?string $serviceClass = null`
  в конце конструктора, default `null`)
- `OperationInventory/Catalog/Export/MarkdownResponseDtoCatalogExporter` —
  multi-service режим включается автоматически, single-client output идентичен
  предыдущему (zero-impact regression)
- Тесты:
  - `tests/Unit/OperationInventory/CompositeOperationInventoryTest.php`
  - `tests/Unit/OperationInventory/Catalog/MultiServiceResponseDtoCatalogFactoryTest.php`
  - `tests/Unit/OperationInventory/Catalog/Export/MarkdownResponseDtoCatalogExporterMultiServiceTest.php`
  - изолированные стабы под `tests/Stubs/CatalogMega/...`
- Документация:
  - `docs/guides/megaclient.md` — раздел «Каталог response DTO по мегаклиенту»
    (factory, trait, экспорт, custom service label, поведение под капотом)
  - `docs/guides/operation-inventory.md` — раздел «Multi-service usage»
  - `docs/glossary/architecture.md` — статьи `CompositeOperationInventory`,
    `MultiServiceResponseDtoCatalogFactory`, `ServiceLabelResolverInterface`
  - `docs/glossary/README.md` — пункты в оглавлении

Тесты apisutra: **710 passed (1704 assertions)** (было 691, +19 новых, регрессий нет).

## Дополнение: унифицированный контракт + merge()
Реализовано вторым шагом для устранения дублирования в DX.

Что добавлено:
- `Contracts/Interfaces/Inventory/ResponseDtoCatalogProviderInterface` —
  унифицированный контракт `responseDtoCatalog(): ResponseDtoCatalog`
- `AbstractClient implements ResponseDtoCatalogProviderInterface` (метод уже
  был, добавлен `#[\Override]`)
- `ProvidesMultiServiceResponseDtoCatalogTrait` — добавлен
  `@phpstan-require-implements ResponseDtoCatalogProviderInterface`; стаб
  `CatalogMegaClient` явно `implements` оба интерфейса
- `MultiServiceResponseDtoCatalogFactory::merge(ResponseDtoCatalogProviderInterface ...$providers)` —
  объединяет произвольный набор провайдеров (одиночные клиенты + мегаклиенты)
  в один сводный `ResponseDtoCatalog` с правильно проставленным `serviceClass`
- Тесты:
  - `tests/Unit/OperationInventory/Catalog/ResponseDtoCatalogProviderInterfaceTest.php`
  - `tests/Unit/OperationInventory/Catalog/MultiServiceResponseDtoCatalogFactoryMergeTest.php`
- Документация:
  - `docs/guides/operation-inventory.md` — раздел про унифицированный контракт
    и `merge()`
  - `docs/guides/megaclient.md` — раздел «Унифицированный обход разнородных
    провайдеров»
  - `docs/glossary/architecture.md` — статья `ResponseDtoCatalogProviderInterface`
  - `docs/glossary/README.md` — пункт в оглавлении

DX итог:
```php
// 1) полиморфный обход разнородных провайдеров
/** @var array<int, ResponseDtoCatalogProviderInterface> $providers */
foreach ([$client, $kontur, $tax] as $provider) {
    $provider->responseDtoCatalog();
}

// 2) единый сводный каталог
$catalog = (new MultiServiceResponseDtoCatalogFactory())->merge(
    $client, $kontur, $tax,
);
```

Тесты apisutra: **721 passed (1724 assertions)** (+11 к предыдущему шагу,
регрессий нет).
