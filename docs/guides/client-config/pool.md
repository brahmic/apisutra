# Pool

Pool — конкурентное выполнение набора независимых запросов. Управляет
параллелизмом и снижает суммарное время ожидания.

## Когда использовать
- массовые запросы по списку идентификаторов
- параллельная загрузка справочников и зависимых ресурсов
- фоновые обновления, где порядок выполнения не важен

## Когда не подходит
- если запросы зависят друг от друга или нужен строгий порядок
- если провайдер требует последовательного доступа (лучше `batch()->sequential()`)

## Настройки
- `concurrency` — число одновременных запросов (задаётся в `pool()`)
- `stopOnFailure` — остановить очередь после первой ошибки (в `PoolConfig`)

По умолчанию `concurrency = 5`, `stopOnFailure = false`.
Меняйте `concurrency`, если провайдер ограничивает параллелизм
или нужно ускорить массовые запросы.

Нюансы:
- pool принимает **только** `RequestInterface`
- `concurrency` фиксируется при старте пула

## Базовая настройка
```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\PoolConfig;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    pool: new PoolConfig(stopOnFailure: true),
);
```

## Использование
```php
$result = $client->pool($requests, 5)->send();
```

## Async‑вариант
`sendAsync()` возвращает `PromiseInterface<PoolResult>`.

## Дополнительные параметры
```php
use Brahmic\ApiSutra\Contracts\Interfaces\Concurrency\ConcurrencyResolverInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Throwable;

final class ExampleConcurrencyResolver implements ConcurrencyResolverInterface
{
    public function getConcurrency(int $pending, int $completed): int
    {
        return $pending > 50 ? 10 : 5;
    }
}

$pool = $client->pool(
    $requests,
    new ExampleConcurrencyResolver(),
);

$result = $pool
    ->withResponseHandler(function (ExecutionResult $result, RequestInterface $request): void {})
    ->withExceptionHandler(function (Throwable $exception, RequestInterface $request): void {})
    ->send();
```

`ConcurrencyResolverInterface` нужен, если хотите динамически менять
параллелизм по ходу выполнения (например, при больших очередях).
