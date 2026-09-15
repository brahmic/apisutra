# Подключить в Laravel

Для этого пути нужно приложение Laravel и установленный пакет ApiSutra.
Package discovery подключает `SdkServiceProvider`; конфигурация вашего SDK остаётся
явной частью приложения.

## Зарегистрировать клиента SDK

[DemoServiceProvider](../../example/sdk/src/Laravel/DemoServiceProvider.php) показывает
полный binding: читает `records.base_url`, создаёт `ClientConfigFactory` и передаёт
зарегистрированный `TransportInterface` в `DemoClient`, затем регистрирует namespace
запросов в `ClientRegistry`. Это делает проверку принадлежности запросов явной.

Скопируйте provider в namespace приложения, зарегистрируйте его в
`bootstrap/providers.php` и задайте `config/records.php` с ключом `base_url`.
Классы примера в настоящем SDK должны иметь обычную Composer PSR-4 регистрацию.

После регистрации, в приложении:

```php
use Example\Records\DemoClient;

$client = app(DemoClient::class);
$record = $client->records()->get(7)->dataOrFail();
```

`app(DemoClient::class)` получает именно настройки binding. Создание отдельно
стоящей переменной `$config` не меняет то, что контейнер передаст клиенту.

## Транспорт и проверка

По умолчанию provider ApiSutra может собрать HTTP-транспорт при доступном PSR-18
клиенте. Для явного транспорта зарегистрируйте свой binding `TransportInterface`.
Для проверки используйте `MockTransport` и локальные фикстуры: пользовательский
binding имеет приоритет перед автоматической регистрацией.

Опубликованный `DemoServiceProvider` проверяется в реальном приложении Laravel:
контейнер получает изменённый URL, тот же mock-транспорт и правила DTO. Этот тест
отделён от standalone-примера и не требует настоящего API.

## Контексты Laravel

Обычный DI SDK-запроса сохраняет заданные значения. Перенос входящего HTTP payload
в SDK-запрос делает явная `RequestFactory`; это другой сценарий.

Для нескольких клиентов, discovery, валидации, `fromLaravel()`, config cache и workers
используйте [контракт интеграции](../../reference/integrations/laravel.md).
Singleton-клиент живёт вместе с контейнером: учётные данные выбирайте в соответствии
с жизненным циклом приложения, а не из случайного текущего HTTP-запроса.
