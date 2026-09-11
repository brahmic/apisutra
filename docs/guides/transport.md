# Transport и HTTP‑слой

Transport отвечает за фактическую отправку HTTP‑запросов.
SDK ожидает реализацию `TransportInterface`.

## Контракт
```php
use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use GuzzleHttp\Promise\PromiseInterface;

interface TransportInterface
{
    public function send(PreparedRequest $request): ProviderResponse;
    public function sendAsync(PreparedRequest $request): PromiseInterface;
}
```

## HttpTransport (PSR‑18/PSR‑17)
Дефолтная реализация использует:
- PSR‑18 `ClientInterface`
- PSR‑17 `RequestFactoryInterface` и `StreamFactoryInterface`

В Laravel `SdkServiceProvider` автоматически создаёт `GuzzleHttpClient` с известным
cURL handler, если пользовательский PSR-клиент/транспорт не задан. Вне Laravel:

```php
use Brahmic\ApiSutra\Transport\HttpTransport;

$transport = HttpTransport::createDefault();
// Передайте $transport в конструктор SDK-клиента.
```

Для этой штатной сборки нужны `guzzlehttp/guzzle` и `ext-curl`. Guzzle HTTP Client
остаётся опциональной зависимостью ядра. PSR-17 фабрики уже входят в зависимости
пакета. Дефолты 30/10 секунд применяются автоматически.

### Собственные HTTP-клиенты и миграция

Конструктор `HttpTransport($httpClient, $requestFactory, $streamFactory)` сохранён.
Чтобы применять SDK-лимиты, PSR-18 клиент дополнительно реализует
`HttpClientOptionsInterface`: `assertSupportsTimeouts(TransportOptions)` проверяет
возможности, `sendWithOptions()` применяет `effective()` непосредственно перед HTTP.
Штатный `GuzzleHttpClient` реализует оба контракта. Для дополнительных параметров:

```php
use Brahmic\ApiSutra\Transport\GuzzleHttpClient;
use GuzzleHttp\Psr7\HttpFactory;

$factory = new HttpFactory();
$transport = new HttpTransport(
    new GuzzleHttpClient(['verify' => '/path/to/ca.pem']),
    $factory,
    $factory,
);
```

Адаптер передаёт `timeout` и `connect_timeout` отдельно для каждой отправки, не меняя
глобальный HTTP-клиент. `http_errors=false` и `allow_redirects=false` сохраняют
обработку 4xx/5xx и redirects в SDK. `sendAsync()` пока выполняет синхронный HTTP
и оборачивает результат в promise; параллельность I/O не гарантируется.

Обычный `GuzzleHttp\Client`, переданный напрямую как PSR-18, не объявляет поддержку
этих опций. Для стандартной сборки замените его на `GuzzleHttpClient` либо используйте
`createDefault()`. Штатный адаптер владеет cURL handler и отклоняет `config['handler']`.
Если нужен существующий Guzzle-клиент с собственным middleware/handler, сохраните
его в своём адаптере `HttpClientOptionsInterface` и явно обеспечьте оба таймаута.
SDK не заменяет пользовательский stack и не определяет capability только по имени класса.

Собственный `TransportInterface` с лимитами реализует `TimeoutAwareTransportInterface`.
При неподдерживаемом ненулевом лимите, включая дефолты, `assertSupportsTimeouts()`
должен выбрасывать `ConfigurationException` с именем неподдерживаемого параметра до
HTTP. Опции берутся из `PreparedRequest::transportOptions`; `effective()` проверяет
deadline и ограничивает таймаут остатком после всех ожиданий. Все копии запроса должны
сохранять опции. Прямой вызов `send()` с PreparedRequest без опций сохраняет старое
поведение: конфиг SDK в таком вызове отсутствует.

Для обычного PSR-18 клиента без адаптера допустим явно выбранный режим
`timeout: 0`, `connectTimeout: 0`, без общего бюджета. Собственные лимиты этого
HTTP-клиента остаются в его ведении. Неизвестный транспорт с активными SDK-лимитами
даёт `configuration_error` до отправки. Контракт, приоритеты и ограничения:
[Timeouts & Delay](client-config/timeouts-delay.md).

## TransportResolver для high-level make API
Для провайдерных SDK (например, `ProviderClient::make(...)`) используйте
`TransportResolver` из ядра:
- если `transport` передан явно — используется он;
- иначе резолв из контейнера через `ContainerProviderInterface`;
- при ошибке кидается стандартизированный `ConfigurationException`.

Это позволяет держать конструктор клиента low-level и предсказуемым
(`new Client($config, $transport)`), а удобный DX вынести в `make(...)`.

```php
use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use Brahmic\ApiSutra\Support\TransportResolver;

public static function make(
    string $accessKey,
    string $secretKey,
    ?TransportInterface $transport = null,
): self {
    $config = ProviderClientConfigFactory::make($accessKey, $secretKey);
    $resolvedTransport = TransportResolver::resolve($transport, $config->containerProvider);

    return new self($config, $resolvedTransport);
}
```

## MockTransport и RecordingTransport
Для тестов доступны:
- `MockTransport` — фейковые ответы, паттерны, последовательности; принимает опции
  для проверки pipeline, фактического HTTP и принудительного прерывания нет
- `RecordingTransport` — запись фикстур в JSON

## PreparedRequest и ProviderResponse
`PreparedRequest` содержит: метод, URL, заголовки, тело/stream, meta и опциональные `transportOptions`.
`ProviderResponse` содержит: статус, заголовки, body, duration.

## Где детали
- Тестирование: `docs/guides/testing.md`
- Быстрый старт: `docs/guides/quickstart.md`
