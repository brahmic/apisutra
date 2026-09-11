# Executors — план тестирования

## Scope
- `BatchExecutor` + стратегии (Sequential/Parallel)
- `PoolExecutor` + concurrency resolver
- `CompositeExecutor`, `DependsOnExecutor`
- `ExecutionErrorFactory`

## Invariants
- Порядок результатов соответствует входным индексам.
- `FailStrategy::FailAll` прерывает выполнение.
- Конфигурационные методы executors иммутабельные (возвращают новый экземпляр).

## Узкие места и целевые тесты
- Сохранение индексов в последовательной стратегии (регрессии при рефакторинге).
- `PoolExecutor` должен применять роль через `RequestOptions`.
- `ConcurrencyResolverInterface` может быть callable или классом — оба пути.

## Сценарии/fixtures (P0)
- **SequentialBatchStrategy indexes**:
  - Вход: 3 запроса с индексами 0..2, failAll=false.
  - Ожидание: индексы результата совпадают с входными.
  - Fixture: MockTransport.
- **PoolExecutor withRole**:
  - Вход: pool с `withRole(Dependency)`.
  - Ожидание: роль присутствует в `RequestOptions`.
  - Fixture: requests + MockTransport.
- **FailAll**:
  - Вход: один failed в середине.
  - Ожидание: выполнение останавливается.
  - Fixture: MockResponse::sequence.

## Unit tests
- `SequentialBatchStrategy` сохраняет индексы.
- `ParallelBatchStrategy` сохраняет индексы и stopOnFailure.
- `PoolExecutor::withRole` влияет на роль в контексте.

## Integration tests
- `BatchExecutor` с `Parallel` и `FailStrategy::Partial`.
- `PoolExecutor` с `ConcurrencyResolverInterface`.
- `CompositeExecutor` агрегирует результаты.
- `DependsOnExecutor` выполняет зависимости последовательно.

## Edge cases
- Неподдерживаемый элемент в batch/pool → `ConfigurationException`.
- Пустой список запросов.

## Fixtures/Mocks
- Mock responses для последовательных/параллельных кейсов.

## Priority
- P0: индексы и fail‑strategy.
- P1: pool concurrency и role.
