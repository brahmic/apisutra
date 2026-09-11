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

В Laravel `SdkServiceProvider` пытается собрать HttpTransport автоматически.

Вне Laravel транспорт нужно передать вручную — без `TransportInterface`
клиент не сможет отправлять запросы.

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
- `MockTransport` — фейковые ответы, паттерны, последовательности
- `RecordingTransport` — запись фикстур в JSON

## PreparedRequest и ProviderResponse
`PreparedRequest` содержит: метод, URL, заголовки, тело/stream и meta.  
`ProviderResponse` содержит: статус, заголовки, body, duration.

## Где детали
- Тестирование: `docs/guides/testing.md`
- Быстрый старт: `docs/guides/quickstart.md`
