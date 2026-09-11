# Container Provider

Настройка интеграции с контейнером через `ContainerProviderInterface`.

## Как работает по умолчанию
- В Laravel `ContainerProviderRegistry` пытается автоматически определить контейнер (auto‑detect).
- Если контейнер недоступен — используется `NullContainerProvider`.

## Если Laravel не используется
Ничего делать не нужно, если вы:
- явно передаёте клиент при отправке (`$client->send($request)`) или через `$request->setClient($client)`
- не используете auto‑resolve клиента и валидатор DTO на уровне контейнера

Что будет без контейнера:
- `ClientResolverInterface` не будет найден автоматически
- валидация `#[Validate]` будет пропущена (нет `validatorFactory`)
- `ClientDiscoveryService` возьмёт basePath из `getcwd()`
- `debug/environment` задаются вручную в `ClientConfig` (не используйте `fromLaravel()`)

Если контейнер всё же нужен — подключите свой провайдер.  
Контейнер нужен, если вы хотите auto‑resolve клиента, DTO‑валидацию
или корректный `basePath`/`environment` без Laravel.

Если нужна валидация DTO без контейнера — можно явно передать фабрику через
`Validator::useFactory()`:
```php
use Brahmic\ApiSutra\VO\Validation\Validator;
use Illuminate\Contracts\Validation\Factory;

$factory = app(Factory::class);
Validator::useFactory($factory);
```

## Переопределение через ClientConfig
```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface;

final class ExampleContainerProvider implements ContainerProviderInterface
{
    public function bound(string $id): bool { return false; }
    public function make(string $id): ?object { return null; }
    public function basePath(): ?string { return null; }
    public function environment(): ?string { return null; }
    public function isDebug(): ?bool { return null; }
    public function validatorFactory(): ?object { return null; }
}

$provider = new ExampleContainerProvider();

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    containerProvider: $provider,
);
```

## Глобальная настройка (bootstrap)
```php
use Brahmic\ApiSutra\Support\ContainerProviderRegistry;

ContainerProviderRegistry::set($provider);
```

## Зачем это нужно
Провайдер влияет на:
- `ClientResolver` для auto‑resolve клиента в запросе
- `Validator` для валидации DTO
- `ClientDiscoveryService` для basePath
- `ClientConfig::fromLaravel` для debug/environment

Также провайдер можно использовать для автоматического получения транспорта
в high-level фабриках клиента через `TransportResolver::resolve(...)`.
Это удобно для `Client::make(...)`, но не меняет low-level контракт конструктора,
где `transport` передаётся явно.
