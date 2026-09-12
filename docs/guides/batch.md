# Batch (runtime)

Batch — это выполнение набора запросов, собранных **в рантайме**.
В отличие от Composite, список запросов формируется на месте, а результат —
`BatchResult` с вложенными `ExecutionResult`.

## Когда использовать
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

## Async‑вариант
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
