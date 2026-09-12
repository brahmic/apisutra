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
- при наличии `#[Validate]` без настроенной фабрики будет `configuration_error`;
  запросам без этих правил фабрика не нужна
- `ClientDiscoveryService` возьмёт basePath из `getcwd()`
- `debug/environment` задаются вручную в `ClientConfig` (не используйте `fromLaravel()`)

Если контейнер всё же нужен — подключите свой провайдер.  
Provider позволяет настроить auto-resolve клиента и получение
`basePath`/`environment` без Laravel. Для валидации достаточно фабрики.

Для валидации без контейнера приложения можно настроить фабрику через
`Validator::useFactory()`; [пример standalone и приоритеты](../validation.md#как-подключается-валидатор).
Приложение Laravel для этого не требуется, компоненты Illuminate Validation нужны
только при использовании соответствующих правил.

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

Явный `containerProvider` клиента определяет фабрику проверки его запросов.
Null от `validatorFactory()` не включает fallback к глобальной фабрике: при наличии
правил это ошибка конфигурации. Ручная проверка уже привязанного запроса использует
тот же источник. Для standalone DTO provider можно передать явно в
`Validator::check($dto, provider: $provider)`.

## Глобальная настройка (bootstrap)
```php
use Brahmic\ApiSutra\Support\ContainerProviderRegistry;

ContainerProviderRegistry::set($provider);
```

## Зачем это нужно
Провайдер влияет на:
- `ClientResolver` для auto‑resolve клиента в запросе
- `Validator` для валидации запросов и DTO по [правилам выбора контекста](../validation.md#приоритет-фабрики)
- `ClientDiscoveryService` для basePath
- `ClientConfig::fromLaravel` для debug/environment

Также провайдер можно использовать для автоматического получения транспорта
в high-level фабриках клиента через `TransportResolver::resolve(...)`.
Это удобно для `Client::make(...)`, но не меняет low-level контракт конструктора,
где `transport` передаётся явно.


В Laravel provider подключается package discovery без обязательной публикации конфига.
Обычный DI сохраняет заданные значения SDK-запроса; перенос входящих HTTP-данных
выполняется явной RequestFactory. Пользовательские bindings имеют приоритет.
[Подключение, миграция и тестирование](../laravel.md).
