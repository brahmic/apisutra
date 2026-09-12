# Быстрый старт

Минимальный путь: конфиг → клиент → запрос → результат.

## 1) Конфигурация клиента
```php
use Brahmic\ApiSutra\Config\ClientConfig;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
);
```

## 2) Клиент
```php
use Brahmic\ApiSutra\Core\AbstractClient;

final class DemoClient extends AbstractClient {}
```

> В Laravel транспорт может быть подставлен автоматически, если доступен PSR‑18 клиент
> (например, Guzzle). В остальных случаях настройте TransportInterface вручную.

## 3) DTO ответа
```php
use Brahmic\ApiSutra\DataTransfer\AbstractResponseDto;

final readonly class UserDto extends AbstractResponseDto
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
```

## 4) Запрос
```php
use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/users')]
#[Returns(UserDto::class, unwrap: 'data')]
final class GetUser extends AbstractRequest
{
    public function __construct(
        #[Query('id')]
        public int $id,
    ) {}
}
```

## 5) Выполнение
```php
// Laravel: транспорт подставится автоматически из контейнера
$client = app(DemoClient::class);

$request = new GetUser(1);
$request->setClient($client);

$user = $request->send()->dataOrFail();
```

## Примечания
- В non‑Laravel окружении передайте `TransportInterface` в конструктор клиента вручную.
- В Laravel клиент может быть подставлен автоматически через `ClientResolver`.
- Для управления ошибками используйте `throwOnErrors` в `ClientConfig` или `dataOrFail()`.

## Дальше
- [Запросы](./requests.md)
- [DTO](./dto.md)
- [Attributes](./attributes/README.md)
- [ClientConfig](./client-config/README.md)
- [Transport](./transport.md)

В Laravel provider подключается package discovery без обязательной публикации конфига.
Обычный DI сохраняет заданные значения SDK-запроса; перенос входящих HTTP-данных
выполняется явной RequestFactory. Пользовательские bindings имеют приоритет.
[Подключение, миграция и тестирование](laravel.md).
