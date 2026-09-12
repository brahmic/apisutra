# Laravel‑интеграция

Пакет поставляется с `SdkServiceProvider`, который подключает DI,
auto-discovery, RequestFactory и адаптер ответов. В Laravel provider подключается
автоматически через package discovery; публиковать конфиг не требуется. При отключённом
Laravel discovery добавьте `SdkServiceProvider::class` в `bootstrap/providers.php`.
Ядро без Laravel продолжает работать через явный SDK-клиент и транспорт.

Приложение создаёт конкретного SDK-клиента со своим baseUrl/auth и регистрирует его
namespace запросов через `ClientRegistry::register()` или существующий discovery.
SDK не выбирает внешний API и credentials за приложение. Пользовательские bindings,
зарегистрированные до provider, сохраняются, включая уже созданные экземпляры.
Объекты ClientConfig/auth/cache собирайте в контейнере; config-файлы должны содержать
сериализуемые значения для `config:cache`.

## Что регистрируется
- `RequestFactoryInterface`
- `ClientResponseAdapterInterface`
- `ClientRegistry` / `ClientResolver`
- `ClientDiscoveryService`
- `ServiceRegistrar` (для мультисервисных клиентов)
- default `TransportInterface` (если не задан)

## DI запросов

Обычный DI сохраняет значения SDK-запроса и привязывает клиента, если он ещё не задан.
Входящий `Illuminate\Http\Request` не читается: `limit=500` в браузере не заменяет
`limit=20`, заданный приложением. В Artisan и заданиях действуют те же правила.
Запросы с обязательными параметрами конструктора создавайте явно или задавайте
обычный Laravel binding.

### Миграция с автоматического заполнения

Контроллеры, полагавшиеся на прежнее автоматическое заполнение, должны вызвать
существующую `RequestFactoryInterface` явно. В примере `GetUser` и `UserClient` —
классы SDK приложения; новый флаг или атрибут не требуется.

```php
use App\Sdk\Users\GetUser;
use App\Sdk\Users\UserClient;
use Brahmic\ApiSutra\Contracts\Interfaces\Factory\RequestFactoryInterface;
use Illuminate\Http\Request;

final class UsersController
{
    public function show(Request $incoming, RequestFactoryInterface $factory, UserClient $client): mixed
    {
        $request = $factory->make(GetUser::class, $incoming);
        return $client->send($request)->dataOrFail();
    }
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
2) создать штатный `GuzzleHttpClient` с cURL handler и PSR-17 фабрики
Если это невозможно — будет `ConfigurationException`.

Штатная сборка требует `guzzlehttp/guzzle` и `ext-curl`, автоматически применяет
таймауты 30/10 секунд. Пользовательские bindings сохраняют приоритет. Переданный
PSR-клиент должен реализовать `HttpClientOptionsInterface` для SDK-лимитов; иначе
отправка с ненулевыми дефолтами вернёт `configuration_error` до HTTP. Миграция и
режим без SDK-лимитов — в [руководстве транспорта](transport.md).


## Валидация и несколько клиентов

При доступном Laravel `validator` проверки `#[Validate]` подключаются автоматически,
без дополнительных настроек клиента. Если клиенту явно передан `containerProvider`,
используется фабрика из этого provider; другие клиенты и глобальный bootstrap её
не подменяют. При объявленных правилах недоступная фабрика даёт `configuration_error`
до HTTP. Запросы без правил и custom preflight не требуют Illuminate Validation.
[Приоритеты, ручная проверка DTO и миграция](validation.md#как-подключается-валидатор).


## Проверяемые контексты

Минимальное Laravel 12 приложение проверяет package discovery, HTTP controller,
явную фабрику, два SDK-клиента, Artisan, последовательные задания в одном процессе
и повторный bootstrap с `config:cache`. Тестовое приложение имеет собственный lock;
Laravel не добавляется в runtime-зависимости ядра. Эти проверки не подтверждают
Laravel 13, Octane или автоматическую изоляцию tenant-клиентов: их жизненный цикл
задаёт приложение.

Для fake HTTP передавайте SDK `TransportInterface` (например, `MockTransport`).
`Laravel Http::fake()` не перехватывает прямой Guzzle/PSR-18 транспорт автоматически.
