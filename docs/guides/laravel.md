# Laravel‑интеграция

Пакет поставляется с `SdkServiceProvider`, который подключает DI,
auto‑discovery, RequestFactory и адаптер ответов.

## Что регистрируется
- `RequestFactoryInterface`
- `ClientResponseAdapterInterface`
- `ClientRegistry` / `ClientResolver`
- `ClientDiscoveryService`
- `ServiceRegistrar` (для мультисервисных клиентов)
- default `TransportInterface` (если не задан)

## DI запросов
Если запрос инжектится через контейнер, он автоматически заполняется
данными из `Illuminate\Http\Request`:
```php
public function show(GetUser $request)
{
    return $request->send()->dataOrFail();
}
```

## RequestFactory и payload‑ключи
`RequestFactory` умеет принимать:
- `Illuminate\Http\Request`
- массив с ключами: `route`, `query`, `body`, `headers`, `files`

```php
use Brahmic\ApiSutra\Laravel\RequestFactory\PayloadKeys;

$payload = [
    PayloadKeys::ROUTE => ['id' => 10],
    PayloadKeys::QUERY => ['page' => 1],
    PayloadKeys::BODY => ['name' => 'Alice'],
    PayloadKeys::HEADERS => ['X-Trace' => '1'],
    PayloadKeys::FILES => [],
];
```

## Auto‑discovery клиентов
```php
use Brahmic\ApiSutra\Resolver\ClientDiscoveryService;

$discovery = app(ClientDiscoveryService::class);
$discovery->registerAuto($client);
```

## Мультисервисные клиенты
Если из контейнера резолвится объект, реализующий `MultiServiceClientInterface`,
`SdkServiceProvider` автоматически вызывает `ServiceRegistrar` и регистрирует
namespace‑ы всех сервис‑клиентов.

## ClientResponseAdapter
Адаптер превращает `ClientResponse` в `SymfonyResponse`:
```php
use Brahmic\ApiSutra\Response\ClientResponseFactoryInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Response\ClientResponseAdapterInterface;

$clientResponse = $factory->make($handle->resolved());
$response = $adapter->toResponse($clientResponse);
```

## ClientConfig::fromLaravel
```php
use Brahmic\ApiSutra\Config\ClientConfig;

$config = ClientConfig::fromLaravel([
    'baseUrl' => config('services.api.base_url'),
]);
```

## Рекомендация: Laravel-конфиги для provider SDK

Для SDK-провайдеров на базе ApiSutra рекомендуем использовать namespaced-конфиг в подпапке:

- **путь:** `config/apisutra/<provider>.php`
- **ключи:** `config('apisutra.<provider>.*')`

Пример для IIDX:
- файл: `config/apisutra/iidx.php`
- чтение: `config('apisutra.iidx.access_key')`, `config('apisutra.iidx.secret_key')`

**Почему так:**
- единый неймспейс для нескольких SDK (`apisutra.iidx`, `apisutra.kontur`, `apisutra.jurisdiction`)
- проще масштабировать и сопровождать
- меньше конфликтов имён в `config/`

Laravel полностью поддерживает подпапки в `config`, включая `config:cache`.

### Backward compatibility

Этот раздел применяется при миграции существующих потребителей, которые ещё
используют старые плоские ключи (`iidx.*`). Для нового SDK достаточно
`apisutra.<provider>.*`; дополнительный слой совместимости не нужен.

Если переход должен сохранять работу старой конфигурации:

1. Читайте сначала новый ключ `apisutra.iidx.*`.
2. Используйте старый `iidx.*` только при отсутствии нового ключа.
3. Если существующему коду нужно читать старый ключ, предоставьте его через
   временный адаптер из уже выбранного значения. Не поддерживайте две
   независимо изменяемые конфигурации.
4. Зафиксируйте версию или условие завершения миграции. После перехода потребителей
   удалите чтение старых ключей и временный адаптер.

## Transport по умолчанию
Если `TransportInterface` не забинден, сервис‑провайдер пытается:
1) найти PSR‑18 + PSR‑17 в контейнере  
2) создать Guzzle‑клиент и фабрики  
Если это невозможно — будет `ConfigurationException`.
