# Подключить без Laravel

Конструктор клиента принимает `ClientConfig` и `TransportInterface` явно. Для
ресурсного вызова контейнер не нужен: ресурс привязывает создаваемый запрос к клиенту.

## Проверить сборку без сети

[Учебный SDK](../../example/sdk/README.md) передаёт `MockTransport` и
[конфигурацию](../../example/sdk/src/Config/ClientConfigFactory.php) в `DemoClient`.
Сначала запустите его, затем замените параметры на данные своего API.

## Настоящий HTTP

Для стандартного транспорта нужны Guzzle HTTP client и расширение PHP `ext-curl`:

```bash
composer require guzzlehttp/guzzle:^7
```

Фрагмент подключения после Composer autoload и автозагрузки вашего SDK:

```php
use Brahmic\ApiSutra\Transport\HttpTransport;
use Example\Records\Config\ClientConfigFactory;
use Example\Records\DemoClient;

$client = new DemoClient(
    ClientConfigFactory::create('https://api.example.test'),
    HttpTransport::createDefault(),
);
```

Замените пример URL настоящим адресом. Само создание транспорта HTTP не выполняет.
Если нужен другой PSR-18 клиент, передайте его вместе с PSR-17 фабриками в
`HttpTransport`. [Контракт транспорта](../../reference/execution/transport.md)
описывает поддержку timeout, redirects и потоков.

## Необязательные интеграции

Внешний кеш использует PSR-16. Без него клиент сохраняет только доступное локальное
состояние механизмов; [кеш ответов](../../reference/execution/cache.md) подключается явно.
Для `#[Validate]` нужна фабрика Illuminate Validation, которую можно передать через
`Validator::useFactory()` без приложения Laravel. [Настройка и приоритеты](../../reference/client/validation.md).

Собственный контейнер подключается через [ContainerProviderInterface](../../reference/client/construction.md).
Это отдельная возможность, а не условие для обычного запроса.
