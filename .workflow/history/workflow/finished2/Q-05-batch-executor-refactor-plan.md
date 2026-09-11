## Цель
Без изменения бизнес‑логики привести `BatchExecutor` к более чистой архитектуре, уменьшить ветвления и улучшить читаемость, используя паттерны из `base-patterns.mdc`.

## Контекст
Файл: `packages/brahmic/apisutra/src/Execution/BatchExecutor.php`  
Зона ответственности: выполнение набора запросов (sequential/parallel), stop‑on‑failure, преобразование ошибок в `ExecutionResult`, сбор `BatchResult`, подготовка запросов (client/role), поддержка различных форматов входа.

## Инварианты (нельзя менять)
- Порядок результатов:
  - Sequential: результаты в исходном порядке до остановки.
  - Parallel: итоговый массив сортируется по индексам и возвращается в исходном порядке.
- Stop‑on‑failure:
  - `FailAll`: в sequential — прекращать цикл после первого failed.
  - `FailAll`: в parallel — прекращать генерацию новых задач, но не прерывать уже запущенные.
- Ошибки async:
  - Если reason не `Throwable`, оборачивать в `ConfigurationException('Ошибка выполнения batch запроса')`.
  - Для ошибки создавать `ExecutionResult` с `ErrorCode::ConnectionFailed`.
- `send()` собирает `BatchResult`:
  - `errors` берутся как **первые** ошибки из failed‑результатов.
  - `status`: `PARTIAL`, если есть и success и failure; иначе `FAILED` или `SUCCESS`.
- `execute()` кидает `ConfigurationException`, если client не задан.
- Входные requests:
  - `RequestInterface` — принимается.
  - `callable` — вызывается с `$parent?->request`, если вернул `RequestInterface`, то принимается.
  - `class-string` — инстанцируется через `instantiateFromParent()`.
  - Остальные элементы игнорируются.
- `instantiateFromParent()`:
  - Берёт параметры конструктора из свойств родительского запроса (`get_object_vars`).
  - Если параметр отсутствует и нет default — передаётся `null`.
- `prepareRequest()`:
  - Для `AbstractRequest` и при наличии `client` — установить client и роль.
- Публичные методы и сигнатуры сохраняются (`execute`, `send`, `sendAsync`, `parallel`, `sequential`, `failStrategy`, `concurrency`).

## Архитектурное решение
1) **Strategy** для режима выполнения:
   - `SequentialBatchStrategy`
   - `ParallelBatchStrategy`
2) **Chain of Responsibility** для нормализации входных запросов:
   - `RequestInstanceResolver`
   - `CallableRequestResolver`
   - `ClassStringRequestResolver`
3) **Context DTO**:
   - `BatchContext` (client, requests, mode, failStrategy, concurrency, parent, role)
4) **Normalizer**:
   - единая нормализация входных requests → `array<RequestInterface>` с сохранением порядка.

## Размещение файлов
`packages/brahmic/apisutra/src/Execution/Batch/`
- `BatchContext.php`
- `BatchStrategyInterface.php`
- `SequentialBatchStrategy.php`
- `ParallelBatchStrategy.php`
- `RequestResolverInterface.php`
- `Resolvers/*`

## План работ
1) Вынести сбор параметров в `BatchContext`.
2) Вынести нормализацию входа в цепочку резолверов (Chain of Responsibility).
3) Вынести выполнение в стратегии:
   - Sequential: простая итерация, stop‑on‑failure.
   - Parallel: `Utils::eachLimit`, stop‑on‑failure, сохранение порядка.
4) Сохранить все публичные методы `BatchExecutor` как фасад‑оркестратор.
5) Проверить, что поведение и порядок результатов идентичны.

## Точки внимания и нюансы
- В parallel‑режиме `shouldStop` останавливает **только** генерацию новых задач, но не отменяет уже запущенные.
- `ksort($results)` + `array_values` обязателен для восстановления порядка.
- Не терять поведение игнорирования невалидных элементов входа.
- В `send()` использовать **первые** ошибки из каждого failed‑результата.
- `resolveStatus()` для пустого набора → `SUCCESS` (как сейчас).
- `sendAsync()` должен продолжать возвращать `PromiseInterface` и оборачивать исключения.
- Не менять текст исключения при отсутствии клиента.

## Критерии приёмки
- Поведение идентично текущему (result order, stop‑on‑failure, error mapping).
- Публичные методы и сигнатуры не меняются.
- Тесты пакета проходят.

## Проверка
- Ручные кейсы:
  - Sequential + FailAll: остановка на первом failed.
  - Parallel + FailAll: прекращение генерации новых задач.
  - Ошибка async → `ExecutionResult` c `ConnectionFailed`.
  - Пустой список → `ResultStatus::SUCCESS`.
  - Callable возвращает `null` → элемент игнорируется.
- Автотесты (если есть для Batch/Execution).

## Риски и меры
- Риск: изменение порядка результатов → фиксировать сортировку по индексу.
- Риск: изменение stop‑on‑failure → отдельные тест‑кейсы.
- Риск: потеря логики `instantiateFromParent` → вынести без изменений.
