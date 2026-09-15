# Несколько сервисов в одном SDK

Мегаклиент — это фасад над **несколькими сервис‑клиентами**, каждый со своим
`ClientConfig` (baseUrl/auth/pagination). Фасад не смешивает конфигурации,
а только маршрутизирует вызовы к нужному сервису.

## Терминология
Определения: [Глоссарий: Архитектура](../../glossary/architecture.md#терминология-мультисервисности).

Коротко: мультисервисность — это несколько **Service** у одного **Vendor**
с **разными глобальными настройками** (baseUrl/auth/pagination/serialization).
Если различий нет — используйте один клиент + ресурсы.

## Когда использовать
- несколько **разных** API‑сервисов от одного поставщика
- разные baseUrl, ключи auth, правила пагинации или сериализации
- отдельные домены/версии API, требующие независимых конфигов

## Когда не нужно
- если глобальные настройки совпадают — используйте один клиент + ресурсы

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
[Версионирование сервисов](../../reference/client/versioning.md).

Для снижения бойлерплейта можно использовать `VersionedResourceTrait`:
- enum‑only переключение версии через `v(UnitEnum $version)`
- единый роутинг ресурсов/запросов по карте версий
- `UnsupportedVersionException` при неподдерживаемой версии

Трейт опционален и не навязывает структуру — бизнес‑методы остаются в ресурсах.

## Рекомендации и ограничения
- не регистрируйте namespace‑ы фасада: регистрируются только сервис‑клиенты
- если сервисы различаются по глобальным настройкам — используйте **отдельные** клиенты
- при нескольких baseUrl учитывайте `RateLimitConfig::key`

## Вызов через фасад SDK

```php
$result = $mega->records()->reports()->get($id)->send();
$status = $mega->billing()->status()->check($account)->send();
```

Методы фасада принадлежат SDK и возвращают его сервисные клиенты.
