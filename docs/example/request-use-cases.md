# Примеры выполнения запросов (DX)

Примеры показывают финальный DX (в будущей архитектуре) после внедрения `ResultHandle` и `ResolvedResult`.
Названия DTO условные.

## 1) Быстрый путь (resolved)

### 1.1 Синхронный запрос (default ResolvedResult)
```php
$resolved = $client->users()->get(1)->resolved();
```
Тип: `ResolvedResult<UserDto>` (default)

### 1.2 Жёсткое получение данных
```php
$user = $client->users()->get(1)->dataOrFail();
```
Тип: `UserDto`

### 1.3 Асинхронный запрос (через resolvedAsync)
```php
$resolved = $client->users()->get(1)->resolvedAsync()->wait();
```
Тип: `ResolvedResult<UserDto>`

### 1.4 Асинхронный запрос (через send mode)
```php
$handle = $client->users()->get(1)->send(mode: SendMode::Async);
$resolved = $handle->resolvedAsync()->wait();
```
Тип: `ResolvedResult<UserDto>`

## 2) Низкоуровневый доступ (ResultHandle)

### 2.1 Только send (получить handle и передать дальше)
```php
$handle = $client->users()->get(1)->send();
```
Тип: `ResultHandle`

### 2.2 Получить handle и работать по шагам
```php
$handle = $client->users()->get(1)->send();
$resolved = $handle->resolved();
```
Тип: `ResultHandle` → `ResolvedResult<UserDto>`

### 2.3 Доступ к сырому результату
```php
$raw = $client->users()->get(1)->send()->raw();
```
Тип: `ExecutionResult`

### 2.4 Async через handle (alias)
```php
$handle = $client->users()->get(1)->sendAsync(); // alias send(mode: SendMode::Async)
$resolved = $handle->resolvedAsync()->wait();
```
Тип: `ResolvedResult<UserDto>`

## 3) Пагинация

### 3.0 send() на пагинированном запросе
```php
$handle = $client->users()->list()->send();
$raw = $handle->raw();
```
Тип: `ResultHandle` → `ExecutionResult` (одна страница по умолчанию)

### 3.1 Одна страница (default)
```php
$resolved = $client->users()->list()->resolved();
```
Тип: `ResolvedResult<UserListDto>`

### 3.2 Все страницы
```php
$resolved = $client->users()
    ->list()
    ->rules(PaginationRule::all())
    ->resolved();
```
Тип: `ResolvedResult<UserCollection>`

```php
$raw = $client->users()
    ->list()
    ->rules(PaginationRule::all())
    ->send()
    ->raw();
```
Тип: `PaginatedResult`

### 3.3 Диапазон страниц
```php
$resolved = $client->users()
    ->list()
    ->rules(PaginationRule::range(2, 4))
    ->resolved();
```
Тип: `ResolvedResult<UserCollection>`

### 3.4 Только N страниц
```php
$resolved = $client->users()
    ->list()
    ->rules(PaginationRule::pages(3))
    ->resolved();
```
Тип: `ResolvedResult<UserCollection>`

## 4) Файлы

### 4.1 Upload файла
```php
$file = FileInput::fromPath('/path/to/report.pdf');
$result = $client->files()->upload($file)->dataOrFail();
```
Тип: `UploadResultDto`

### 4.2 Download файла
```php
$resolved = $client->files()->download($id)->resolved();
$file = $resolved->data();
```
Тип: `ResolvedResult<FileResponse>`

## 5) Архивы

### 5.1 Архив в ответе
```php
$archive = $client->reports()->archive($range)->dataOrFail();
```
Тип: `ArchiveResponse`

## 6) Частичный результат
```php
$resolved = $client->users()->list()->rules(PaginationRule::all())->resolved();
if ($resolved->isPartial()) {
    $errors = $resolved->errors();
}
```
Тип: `ResolvedResult<UserCollection>`

## 7) Доступ к raw ответу провайдера
```php
$raw = $client->users()->get(1)->send()->raw();
$payload = $raw->debug?->response?->json();
```
Тип: `ExecutionResult`

## 8) Клиентский результат (MClientResult)

### 8.1 resolved() возвращает клиентский тип
```php
$resolved = $client->users()->get(1)->resolved();
```
Тип: `MClientResult<UserDto>` (если задана фабрика)

### 8.2 dataOrFail остаётся чистыми данными
```php
$user = $client->users()->get(1)->dataOrFail();
```
Тип: `UserDto`

## 9) Клиентский ответ (ClientResponse)

### 9.1 Ответ по умолчанию
```php
$resolved = $client->users()->get(1)->resolved();
$response = $client->response($resolved);
```
Тип: `ClientResponse` (default)

### 9.2 Кастомный ответ клиента
```php
$resolved = $client->users()->get(1)->resolved();
$response = $client->response($resolved); // MClientResponse
```
Тип: `MClientResponse` (если задана factory)

## 10) Default pagination rule в ClientConfig

### 10.1 Клиент с дефолтным правилом (например, pages(2))
```php
$client = new MyClient(
    config: new ClientConfig(
        baseUrl: 'https://api.test',
        paginationRule: PaginationRule::pages(2),
    ),
);

$resolved = $client->users()->list()->resolved();
```
Тип: `ResolvedResult<UserCollection>`

### 10.2 Локальное переопределение правила
```php
$resolved = $client->users()
    ->list()
    ->rules(PaginationRule::range(3, 4))
    ->resolved();
```
Тип: `ResolvedResult<UserCollection>`

## 11) Асинхронность через SendMode

### 11.1 Явный async mode
```php
$handle = $client->users()->get(1)->send(mode: SendMode::Async);
$resolved = $handle->resolvedAsync()->wait();
```
Тип: `ResolvedResult<UserDto>`

## 12) Кастомный результат клиента (Factory)

### 12.1 Реализация фабрики результата
```php
final class MClientResultFactory implements ResolvedResultFactoryInterface
{
    public function make(ExecutionResult $result): ResolvedResultInterface
    {
        return new MClientResult($result);
    }
}
```

### 12.2 Подключение фабрики в клиенте
```php
$client = new MyClient(
    config: new ClientConfig(
        baseUrl: 'https://api.test',
        resolvedResultFactory: new MClientResultFactory(),
    ),
);

$resolved = $client->users()->get(1)->resolved();
```
Тип: `MClientResult<UserDto>`

## 13) Кастомный ответ клиента (Factory)

### 13.1 Реализация фабрики ответа
```php
final class MClientResponseFactory implements ClientResponseFactoryInterface
{
    public function make(ResolvedResultInterface $result): ClientResponse
    {
        return new MClientResponse(
            status: 200,
            headers: ['X-Client' => 'm-client'],
            body: $result->data(),
        );
    }
}
```

### 13.2 Подключение фабрики и получение ответа
```php
$client = new MyClient(
    config: new ClientConfig(
        baseUrl: 'https://api.test',
        responseFactory: new MClientResponseFactory(),
    ),
);

$resolved = $client->users()->get(1)->resolved();
$response = $client->response($resolved);
```
Тип: `MClientResponse`

### 13.3 Кастомный маппинг ошибок без своей фабрики
```php
final class MClientErrorMapper implements ClientErrorMapperInterface
{
    public function map(RequestError $error): ClientError
    {
        return new ClientError(
            providerCode: (string) ($error->response?->status ?? ''),
            sdkCode: $error->code,
            clientCode: 'client.custom',
            appCode: 'APP-001',
            message: $error->message,
            context: $error->context,
            nested: [],
            requestClass: $error->requestClass,
        );
    }

    public function status(ErrorCollection $errors): int
    {
        return 422;
    }
}

$client = new MyClient(
    config: new ClientConfig(
        baseUrl: 'https://api.test',
        errorMapper: new MClientErrorMapper(),
    ),
);

$response = $client->response($client->users()->get(1)->resolved());
```
Тип: `ClientResponse` (но с кастомными ошибками)

### 13.4 Laravel адаптер для ClientResponse
```php
use Brahmic\ApiSutra\Contracts\Response\ClientResponseAdapterInterface;

$resolved = $client->users()->get(1)->resolved();
$clientResponse = $client->response($resolved);

$adapter = app(ClientResponseAdapterInterface::class);
return $adapter->toResponse($clientResponse);
```
Тип: `\Symfony\Component\HttpFoundation\Response`
