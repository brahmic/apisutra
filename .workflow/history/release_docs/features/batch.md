# Batch (Runtime)

Выполнение коллекции запросов в рантайме.

## Концепция

| Тип | Определение | Результат |
|-----|-------------|-----------|
| Composite | Compile-time (интерфейс) | `ExecutionResult` с агрегированным DTO |
| Batch | Runtime (метод клиента) | `BatchResult` (extends ExecutionResult) |

**Общий механизм:** Оба типа используют `BatchExecutor` для параллельного/последовательного выполнения группы запросов.

**Единая иерархия результатов:**
```
ResultInterface
     ↑
ExecutionResult implements ResultInterface
     ↑
├── PaginatedResult extends ExecutionResult
└── BatchResult extends ExecutionResult
```

---

## Использование

```php
// Динамическая коллекция запросов
$requests = RequestCollection::make([
    new GetOrder($orderId1),
    new GetOrder($orderId2),
    new GetInvoice($invoiceId),
]);

$results = $client->batch($requests)->send();
```

### Требования к элементам

Допустимые типы элементов:
- `RequestInterface` (инстанс запроса)
- `class-string` запроса
- `callable`, возвращающий `RequestInterface`

Неподдерживаемые элементы или `callable`, вернувший не‑`RequestInterface`,
приводят к `ConfigurationException` (fail‑fast).

**Результат — BatchResult (extends ExecutionResult):**

```php
$result->isSuccess();        // Все успешны
$result->isPartial();        // Часть успешна
$result->results();          // Все вложенные ExecutionResult
$result->successful();       // Только успешные
$result->failed();           // Только неуспешные
$result->get(0);             // По индексу → ExecutionResult
$result->getByClass(GetOrder::class);  // По классу запроса
```

Итоговый статус `BatchResult`:
- `SUCCESS` — если все результаты успешны
- `FAILED` — если все результаты неуспешны
- `PARTIAL` — во всех остальных случаях

---

## Конфигурация

### Метод с параметрами

```php
$client->batch($requests)
    ->parallel()              // Параллельное выполнение
    ->failStrategy(FailStrategy::Partial)  // Продолжать при ошибках
    ->send();
```

### Через BatchConfig

```php
$config = new BatchConfig(
    mode: ExecutionMode::Parallel,
    failStrategy: FailStrategy::Partial,
    concurrency: 5,  // Макс. параллельных запросов
);

$client->batch($requests, $config)->send();
```

---

## Результат

### BatchResult (extends ExecutionResult)

```php
readonly class BatchResult extends ExecutionResult
{
    // Наследует от ExecutionResult:
    // - status, errors, debug, traceId, audit
    // - isSuccess(), isPartial(), isFailed(), hasData(), hasErrors()
    
    // Специфичные методы:
    public function results(): ResultCollection;          // $this->nested — все результаты
    public function successful(): ResultCollection;       // только успешные
    public function failed(): ResultCollection;           // только неуспешные
    public function get(int $index): ?ExecutionResult;
    public function getByClass(string $class): ResultCollection;
    public function meta(): BatchMeta;         // типизированная мета
}

readonly class BatchMeta implements ResultMeta
{
    public int $total;       // всего запросов
    public int $successful;  // успешных
    public int $failed;      // неуспешных
    public int $partial;     // частично успешных
}
```

**BatchResult vs PoolResult:**
- `BatchResult` — результат runtime batch, где запросы выполняются как единый
  набор и возвращается типизированная meta (BatchMeta).
- `PoolResult` — результат pool‑выполнения с упором на параллелизм и управление
  concurrency; meta отличается и ориентирована на массовое выполнение.

### Итерация по результатам

```php
foreach ($result->results() as $item) {
    if ($item->isSuccess()) {
        process($item->data);
    } else {
        log($item->errors);
    }
}
```

---

## Сравнение с Composite

| Аспект | Composite | Batch |
|--------|-----------|-------|
| Определение | Класс + интерфейс | Рантайм (метод клиента) |
| Запросы | Фиксированные | Динамические |
| Результат | Один DTO (агрегация) | Коллекция результатов |
| Метод агрегации | `aggregate()` в классе | — |
| Конфигурация | `#[Execution]` атрибут | Параметры метода |

### Когда что использовать

**Composite:**
- Логически связанные запросы
- Нужна агрегация в один DTO
- Повторяющийся сценарий (определён в коде)

**Batch:**
- Динамический список запросов
- Каждый результат нужен отдельно
- Однократный сценарий (формируется в рантайме)

---

## Внутренняя реализация

```
┌─────────────────────────────────────────────────┐
│                  BatchExecutor                  │
├─────────────────────────────────────────────────┤
│  - Принимает RequestCollection                  │
│  - Выполняет Sequential / Parallel              │
│  - Применяет FailStrategy                       │
│  - Возвращает ResultCollection                  │
└─────────────────────────────────────────────────┘
           ↑                           ↑
           │                           │
    ┌──────┴──────┐             ┌──────┴──────┐
    │  Composite  │             │    Batch    │
    │   Request   │             │   (runtime) │
    ├─────────────┤             ├─────────────┤
    │ aggregate() │             │     —       │
    │     ↓       │             │             │
    │ ExecutionResult            │ BatchResult
    │ (один DTO)  │             │ (extends ExecutionResult) │
    └─────────────┘             └─────────────┘
```

**BatchExecutor** — общий компонент:
- Для Composite: вызывается из pipeline, результаты передаются в `aggregate()`
- Для Batch: вызывается напрямую из клиента, результаты оборачиваются в `BatchResult`
