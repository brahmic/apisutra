# Мегаклиент и мультисервисная архитектура

Мегаклиент — это фасад над **несколькими сервис‑клиентами**, каждый со своим
`ClientConfig` (baseUrl/auth/pagination). Фасад не смешивает конфигурации,
а только маршрутизирует вызовы к нужному сервису.

## Терминология
Определения: [Глоссарий: Архитектура](../glossary/architecture.md#терминология-мультисервисности).

Коротко: мультисервисность — это несколько **Service** у одного **Vendor**
с **разными глобальными настройками** (baseUrl/auth/pagination/serialization).
Если различий нет — используйте один клиент + ресурсы.

## Когда использовать
- несколько **разных** API‑сервисов от одного поставщика
- разные baseUrl, ключи auth, правила пагинации или сериализации
- отдельные домены/версии API, требующие независимых конфигов

## Когда не нужно
- если глобальные настройки совпадают — используйте один клиент + ресурсы

## Контракт мегаклиента
`MultiServiceClientInterface` требует вернуть список сервис‑клиентов.
```php
use Brahmic\ApiSutra\Contracts\Interfaces\Core\MultiServiceClientInterface;

final readonly class MegaClient implements MultiServiceClientInterface
{
    public function __construct(
        private RealtyClient $realty,
        private TaxClient $tax,
    ) {}

    public function services(): array
    {
        return [$this->realty, $this->tax];
    }
}
```

## Сервисный клиент (отдельный конфиг)
```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use Brahmic\ApiSutra\Core\AbstractClient;

final class RealtyClient extends AbstractClient
{
    public function __construct(ClientConfig $config, TransportInterface $transport)
    {
        parent::__construct($config, $transport);
    }
}
```

## Zero‑config регистрация сервисов
`ServiceRegistrar` регистрирует namespace‑ы каждого сервис‑клиента в `ClientRegistry`.
Если ручной override не нужен, ничего дополнительно на клиенте не настраивайте.
Вне Laravel вызовите регистрацию вручную (через контейнер или напрямую).

```php
use Brahmic\ApiSutra\Resolver\ServiceRegistrar;
use Brahmic\ApiSutra\Resolver\ClientRegistry;
use Brahmic\ApiSutra\Resolver\RequestNamespaceDetector;

// $registry и $detector получены из контейнера
$registrar = new ServiceRegistrar($registry, $detector);
$registrar->register($mega->services());
```

## Опциональный override namespace‑ов
Если авто‑детект не подходит (нестандартные namespace‑ы), реализуйте
`RequestNamespaceProviderInterface` на сервис‑клиенте:
```php
use Brahmic\ApiSutra\Contracts\Interfaces\Resolver\RequestNamespaceProviderInterface;

final class LegacyClient extends AbstractClient implements RequestNamespaceProviderInterface
{
    public function requestNamespaces(): array
    {
        return [
            'Vendor\\Legacy\\Requests',
            'Vendor\\Legacy\\Resources',
        ];
    }
}
```

## Laravel‑интеграция
`SdkServiceProvider` автоматически вызывает `ServiceRegistrar`, когда из контейнера
резолвится объект, реализующий `MultiServiceClientInterface`.
Нужно лишь зарегистрировать мегаклиент и сервис‑клиенты в контейнере.

## Версии сервисов (опционально)
Если у одного сервиса появляются версии (`v1/v2/v3`), рекомендуемый DX:
- canonical: `->service()->v3()->resource()->method()`
- альтернатива: `->service()->useVersion(ServiceVersion::V3)->resource()->method()`

Полная стратегия и правила выбора — в гайде
[Версионирование сервисов](./versioning.md).

Для снижения бойлерплейта можно использовать `VersionedResourceTrait`:
- enum‑only переключение версии через `v(UnitEnum $version)`
- единый роутинг ресурсов/запросов по карте версий
- `UnsupportedVersionException` при неподдерживаемой версии

Трейт опционален и не навязывает структуру — бизнес‑методы остаются в ресурсах.

## Каталог response DTO по мегаклиенту
Если нужен один общий перечень всех DTO, которые возвращают сервисы мегаклиента
(sync через `Returns`, async-final через `ContinuationResult`, download через
`#[Download]`), apisutra даёт готовый multi-service слой поверх
[`ResponseDtoCatalog`](./operation-inventory.md#responsedtocatalog).

### Через factory (канонический путь)
```php
use Brahmic\ApiSutra\OperationInventory\Catalog\MultiServiceResponseDtoCatalogFactory;

$catalog = (new MultiServiceResponseDtoCatalogFactory())->fromMultiService($mega);

foreach ($catalog->usages() as $dtoClass => $usages) {
    foreach ($usages as $usage) {
        $usage->serviceClass;   // FQCN сервис-клиента (например, RealtyClient::class)
        $usage->resourceLabel;  // 'Reports / Tasks' внутри сервиса
        $usage->kind;           // ResponseDtoKind::Sync | AsyncFinal | Download
    }
}
```

### Унифицированный обход разнородных провайдеров
Любой провайдер каталога — одиночный клиент или мегаклиент — имплементирует
`ResponseDtoCatalogProviderInterface`. Это позволяет обходить смешанный набор
SDK единым циклом:

```php
use Brahmic\ApiSutra\Contracts\Interfaces\Inventory\ResponseDtoCatalogProviderInterface;

/** @var array<int, ResponseDtoCatalogProviderInterface> $providers */
$providers = [$realtyClient, $kontur, $tax];

foreach ($providers as $provider) {
    foreach ($provider->responseDtoCatalog()->usages() as $dtoClass => $usages) {
        // ...
    }
}
```

Если нужен **один сводный каталог** по нескольким провайдерам (без ручного
склеивания), используйте `merge()` с varargs:

```php
$catalog = (new MultiServiceResponseDtoCatalogFactory())->merge(
    $realtyClient,   // одиночный AbstractClient
    $kontur,         // мегаклиент (раскрывается в services())
    $tax,            // ещё мегаклиент
);
```

Под капотом `merge()`:

- разворачивает мегаклиенты в их `services()`
- проставляет `serviceClass` для каждого usage:
  - для services() мегаклиента — FQCN сервиса
  - для одиночного клиента — FQCN самого клиента
- если один и тот же `requestClass` встречается у нескольких провайдеров,
  выигрывает первый по порядку (детерминированно)

`$usage->serviceClass` — это FQCN. Презентационный label (например, `RealtyClient`
→ `"Realty"`) — отдельный концерн (см. ниже про exporter и
`ServiceLabelResolverInterface`).

### Через trait (sugar для мегаклиента)
Чтобы получить `$mega->responseDtoCatalog()` без бойлерплейта, подмешайте trait:

```php
use Brahmic\ApiSutra\Contracts\Interfaces\Core\MultiServiceClientInterface;
use Brahmic\ApiSutra\Traits\ProvidesMultiServiceResponseDtoCatalogTrait;

final class MegaClient implements MultiServiceClientInterface
{
    use ProvidesMultiServiceResponseDtoCatalogTrait;

    public function services(): array { /* ... */ }
}

$catalog = $mega->responseDtoCatalog();   // кешируется на инстансе
```

Trait — тонкий wrapper над factory, ничего больше. `MultiServiceClientInterface`
осознанно остаётся одно-методным; этот trait — DX-слой, не часть контракта.

### Экспорт в файл
Built-in `MarkdownResponseDtoCatalogExporter` автоматически распознаёт
multi-service режим (по наличию `serviceClass` в usages) и:

- добавляет секцию `## Сервисы` со счётчиками `Sync / AsyncFinal / Download` по сервисам
- добавляет колонку `Service` во все таблицы (по ресурсам, by-DTO usages, downloads)

Single-client output (когда `serviceClass` ни у кого не заполнен) остаётся
полностью прежним — никакого drift.

```php
use Brahmic\ApiSutra\OperationInventory\Catalog\Export\MarkdownResponseDtoCatalogExporter;
use Brahmic\ApiSutra\OperationInventory\Catalog\Export\ResponseDtoCatalogWriter;

$writer = new ResponseDtoCatalogWriter([
    new MarkdownResponseDtoCatalogExporter(),
]);

$writer->writeTo('/path/dto-catalog.md', $mega->responseDtoCatalog());
```

### Кастомный label сервисов
Если короткое имя класса (`RealtyClient`) не устраивает в заголовках, передайте
свой резолвер в exporter:

```php
use Brahmic\ApiSutra\Contracts\Interfaces\Inventory\ServiceLabelResolverInterface;

$resolver = new class implements ServiceLabelResolverInterface {
    public function resolve(string $serviceClass): string
    {
        return match ($serviceClass) {
            RealtyClient::class => 'Realty',
            TaxClient::class    => 'Tax',
            default             => $serviceClass,
        };
    }
};

$exporter = new MarkdownResponseDtoCatalogExporter($resolver);
```

В сами `ResponseDtoUsage` label не попадает (там хранится сырой FQCN) —
переименование чисто презентационное.

### Поведение под капотом
- `MultiServiceResponseDtoCatalogFactory` собирает `operationInventory()` со
  всех сервис-клиентов и складывает их в `CompositeOperationInventory`
- `serviceClass` проставляется на каждый `ResponseDtoUsage` по карте
  `requestClass → serviceClass`, построенной за один проход по inventory
- если один `requestClass` встречается у нескольких сервисов, выигрывает первый
  по порядку `services()` (детерминированно)
- сервисы остаются изолированы: resource grouping не объединяется между
  сервисами, namespace-эвристика разводит `Reports` у `RealtyClient` и
  `Reports` у `TaxClient` по разным сервисам автоматически

## Рекомендации и ограничения
- не регистрируйте namespace‑ы фасада: регистрируются только сервис‑клиенты
- если сервисы различаются по глобальным настройкам — используйте **отдельные** клиенты
- при нескольких baseUrl учитывайте `RateLimitConfig::key`

