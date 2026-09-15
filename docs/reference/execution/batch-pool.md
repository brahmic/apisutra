# Batch и pool

Pool управляет конкурентностью набора независимых запросов. Фактический
параллелизм HTTP зависит от транспорта.

## Когда использовать pool
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

## Promise для pool
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

Batch — это выполнение набора запросов, собранных **в рантайме**.
В отличие от Composite, список запросов формируется на месте, а результат —
`BatchResult` с вложенными `ExecutionResult`.

## Когда использовать batch
- массовые запросы по списку идентификаторов
- сбор данных из разных endpoint’ов без жёсткой схемы
- простая конкурентная обработка (sequential/parallel)

## Базовое использование
```php
use Brahmic\ApiSutra\Enums\Execution\ExecutionMode;
use Brahmic\ApiSutra\Enums\Execution\FailStrategy;

$result = $client
    ->batch($requests)
    ->withMode(ExecutionMode::Parallel)
    ->withFailStrategy(FailStrategy::Partial)
    ->withConcurrency(10)
    ->send();
```

## Конфигурация через BatchConfig
```php
use Brahmic\ApiSutra\Config\BatchConfig;

$config = new BatchConfig(
    mode: ExecutionMode::Parallel,
    failStrategy: FailStrategy::Partial,
    concurrency: 10,
);

$result = $client->batch($requests, $config)->send();
```

По умолчанию: `Sequential`, `FailAll`, `concurrency = 5`.
Если не нужна кастомизация, достаточно базового `batch($requests)`.

## Какие элементы допустимы в batch
Batch принимает **RequestInterface** или специальные формы:

1) **Готовый инстанс запроса**
```php
$requests = [
    new GetUser($id),
    new GetOrders($id),
];
```

2) **Callable** (получает parent‑request или null)
```php
$requests = [
    fn ($parent) => new GetUser($parent?->userId ?? '0'),
];
```

3) **class-string** (конструктор берёт значения из parent‑request)
```php
$requests = [
    GetUser::class,
];
```

Нюанс: если parent‑request отсутствует, параметры конструктора заполняются
дефолтами или `null`.

## Режимы выполнения
- `ExecutionMode::Sequential` — строгий порядок
- `ExecutionMode::Parallel` — параллельное выполнение с ограничением concurrency

## FailStrategy
- `FailAll` — остановить выполнение при первой ошибке
- `Partial` / `IgnoreErrors` — продолжать и вернуть частичный результат

## Результат Batch
`send()` возвращает `BatchResult`, где:
- `results()` — коллекция всех результатов
- `successful()` / `failed()` — отфильтрованные коллекции
- `meta()` — `BatchMeta` (total/success/failed/partial)

## Promise для batch
`sendAsync()` возвращает `PromiseInterface<BatchResult>`.

## Pool vs Batch
- **Batch** — стратегия выполнения + fail‑policy (sequential/parallel).
- **Pool** — конкурентный запуск независимых запросов с обработчиками
  результатов и исключений.
Если важен порядок или нужна fail‑strategy — используйте batch.
Pool удобен для обработки результатов по мере их готовности.

Parallel и PromiseInterface не гарантируют параллельный HTTP: штатный Guzzle-адаптер
сейчас выполняет I/O синхронно. Concurrency ограничивает планирование задач; ускорение
зависит от поддержки асинхронного I/O транспортом.

## Массовая загрузка (Batch)
```php
$result = $client
    ->batch([GetUser::class, GetOrders::class])
    ->parallel()
    ->concurrency(5)
    ->send();
```

## Быстрый fan‑out (Pool)
```php
$result = $client->pool($requests, 10)->send();
```
