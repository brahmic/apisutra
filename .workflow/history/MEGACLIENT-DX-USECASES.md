# DX: мегаклиент как фасад над сервисами

## Ключевая идея
Мегаклиент — это фасад, который агрегирует **несколько независимых клиентов**,
каждый со своим `ClientConfig` (baseUrl/auth/pagination). Фасад не смешивает
конфигурации и только маршрутизирует вызовы к нужному сервису.

## DX‑сценарии
1) **Единая точка входа**
```php
$kontur->realty()->reports()->get($id)->send();
$kontur->tax()->status()->check($inn)->send();
```

2) **Вложенные ресурсы**
```php
$kontur->realty()->objects()->documents()->list($objectId)->send();
```

3) **DI без ручного setClient()**
```php
public function __invoke(GetReportRequest $request): Response
{
    $result = $request->send();
    return response()->json($result->data);
}
```

## Пример структуры папок
```
Kontur/
  Realty/
    Client/RealtyClient.php
    Resources/ReportsResource.php
    Requests/GetReportRequest.php
  Tax/
    Client/TaxClient.php
    Resources/StatusResource.php
    Requests/CheckStatusRequest.php
  KonturClient.php
```

## Пример реализации
### Фасад мегаклиента
```php
<?php

declare(strict_types=1);

namespace Kontur;

use Kontur\Realty\Client\RealtyClient;
use Kontur\Tax\Client\TaxClient;
use Kontur\Realty\Resources\RealtyRootResource;
use Kontur\Tax\Resources\TaxRootResource;

final readonly class KonturClient
{
    public function __construct(
        private RealtyClient $realty,
        private TaxClient $tax,
    ) {}

    public function realty(): RealtyRootResource
    {
        return new RealtyRootResource($this->realty);
    }

    public function tax(): TaxRootResource
    {
        return new TaxRootResource($this->tax);
    }
}
```

### Сервисный клиент
```php
<?php

declare(strict_types=1);

namespace Kontur\Realty\Client;

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

### Корневой ресурс сервиса
```php
<?php

declare(strict_types=1);

namespace Kontur\Realty\Resources;

use Brahmic\ApiSutra\Core\AbstractResource;

final class RealtyRootResource extends AbstractResource
{
    public function reports(): ReportsResource
    {
        return $this->resource(ReportsResource::class);
    }
}
```

### Ресурс и запрос
```php
<?php

declare(strict_types=1);

namespace Kontur\Realty\Resources;

use Brahmic\ApiSutra\Core\AbstractResource;
use Kontur\Realty\Requests\GetReportRequest;

final class ReportsResource extends AbstractResource
{
    public function get(string $id): GetReportRequest
    {
        return $this->request(GetReportRequest::class, $id);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace Kontur\Realty\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Path;
use Brahmic\ApiSutra\Attributes\Request\AuthScope;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/reports/{id}')]
#[AuthScope('realty')]
final class GetReportRequest extends AbstractRequest
{
    public function __construct(
        #[Path('id')]
        public string $id,
    ) {}
}
```

## Использование новых контрактов (мегаклиент + регистрация сервисов)
### Контракт мегаклиента
```php
<?php

declare(strict_types=1);

namespace Kontur;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\MultiServiceClientInterface;
use Kontur\Realty\Client\RealtyClient;
use Kontur\Tax\Client\TaxClient;

final readonly class KonturClient implements MultiServiceClientInterface
{
    public function __construct(
        private RealtyClient $realty,
        private TaxClient $tax,
    ) {}

    public function services(): array
    {
        return [
            $this->realty,
            $this->tax,
        ];
    }
}
```

### Регистрация сервисов (без Laravel)
```php
$kontur = new KonturClient($realtyClient, $taxClient);
$registrar = new ServiceRegistrar($clientRegistry, $namespaceDetector);
$registrar->register($kontur->services());
```

### Регистрация сервисов (Laravel)
```php
$this->app->singleton(KonturClient::class, function ($app) {
    return new KonturClient(
        $app->make(RealtyClient::class),
        $app->make(TaxClient::class),
    );
});

// SdkServiceProvider обнаруживает MultiServiceClientInterface и вызывает ServiceRegistrar автоматически.
```

## Пример ServiceProvider для Контура (база задаётся в SP, env опционален)
```php
<?php

declare(strict_types=1);

namespace Kontur\Laravel;

use Brahmic\ApiSutra\Auth\ApiKeyAuthenticator;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use Illuminate\Support\ServiceProvider;
use Kontur\KonturClient;
use Kontur\Realty\Client\RealtyClient;
use Kontur\Tax\Client\TaxClient;

final class KonturServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RealtyClient::class, function ($app) {
            $config = ClientConfig::fromLaravel([
                'baseUrl' => (string) (config('kontur.realty.base_url') ?: 'https://api.kontur.ru/realty'),
                'auth' => new ApiKeyAuthenticator(
                    key: (string) config('kontur.realty.api_key'),
                    header: 'X-Api-Key',
                ),
            ]);

            return new RealtyClient($config, $app->make(TransportInterface::class));
        });

        $this->app->singleton(TaxClient::class, function ($app) {
            $config = ClientConfig::fromLaravel([
                'baseUrl' => (string) (config('kontur.tax.base_url') ?: 'https://api.kontur.ru/tax'),
                'auth' => new ApiKeyAuthenticator(
                    key: (string) config('kontur.tax.api_key'),
                    header: 'X-Api-Key',
                ),
            ]);

            return new TaxClient($config, $app->make(TransportInterface::class));
        });

        $this->app->singleton(KonturClient::class, function ($app) {
            return new KonturClient(
                $app->make(RealtyClient::class),
                $app->make(TaxClient::class),
            );
        });
    }
}
```

## Как это работает
1) **Фасад** хранит сервис‑клиенты и отдаёт корневые ресурсы.
2) **Ресурсы** создают запросы через `AbstractResource::request()` и сразу
   привязывают правильный клиент.
3) **Каждый сервис** имеет свой `ClientConfig`, свой `baseUrl/auth/pagination`.
4) **DI/auto‑resolve** работает через `ClientRegistry`: запросы по namespace
   резолвятся в нужный сервис‑клиент.

## Автоскан и DI (ожидаемая схема)
- Каждый сервис регистрируется в `ClientRegistry` со своим набором namespace‑ов.
- По умолчанию используется `RequestNamespaceDetector->detect($client)`.
- Опционально можно реализовать `RequestNamespaceProviderInterface` в клиенте.
- При DI‑создании `AbstractRequest` подставляется корректный клиент.

## Ограничения и правила
- Не регистрировать один фасад‑клиент на все namespace, иначе смешаются
  `baseUrl/auth/pagination`.
- Для сервисов с разной глобальной конфигурацией использовать отдельные клиенты.
