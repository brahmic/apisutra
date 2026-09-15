# Выполнение запросов

Этот документ описывает выбор исполнителя и связи между отдельными отправками.
Внутренние стадии одной отправки находятся в [пайплайне](pipeline.md), общая карта —
в [архитектуре](architecture.md).

## Вход и runtime-опции

[AbstractRequest](../../src/Core/AbstractRequest.php) хранит декларацию операции.
[RequestExecution](../../src/Request/RequestExecution.php) связывает её с
`RequestOptions` и `PaginationOptions`: цепочки `with*()` создают обёртки с новыми
значениями опций. Обёртка передаётся в клиент целиком, чтобы сохранить overrides.

[AbstractClient](../../src/Core/AbstractClient.php) отправляет запрос и возвращает
`ResultHandle`. В синхронном пути он передаёт запрос в `RequestResolver`, добавляет
метаданные результата и связывает handle с клиентом и исходным запросом.
Последние две ссылки нужны в том числе для последующего `await()`.

В начале `Pipeline::execute()` обёртка разворачивается: исходный запрос становится
`context.request`, опции — `context.options` и `context.paginationOptions`.
Если обёртки нет, runtime-опции могут поступить через `RequestOptionsProviderInterface`.
Публичные способы получения результата — в
[ResultHandle](../reference/results/handles.md).

## Выбор потока

| Сценарий | Кто организует выполнение | Что происходит дальше |
| --- | --- | --- |
| Одиночный запрос | RequestResolver | Один вызов исполнителя Pipeline |
| Несколько страниц | Paginator | Последовательные вызовы исполнителя с опциями страницы/курсора |
| Composite | PipelineCompositeHandler → CompositeFlow | Дочерние запросы и агрегация их результатов |
| Depends-on | PipelineCompositeHandler → CompositeFlow | Зависимости, применение их данных, затем основная отправка |
| Batch | BatchExecutor | Нормализация списка и последовательная или параллельная стратегия |
| Pool | PoolExecutor | Подача запросов через promise API с ограничением concurrency |
| Continuation | ContinuationService | Оценка состояния операции, poll-запросы, получение финального значения |

Эти механизмы могут сочетаться: например, дочерний запрос composite может обходить
страницы. Передача через клиент сохраняет настройки исполнения и гидратации.

## Одиночное выполнение и пагинация

[RequestResolver](../../src/Request/RequestResolver.php) выбирает `PaginationRule`:
runtime override имеет приоритет над правилом клиента. При `Single` он вызывает
переданный executor напрямую; остальные режимы делегирует
[Paginator](../../src/Pagination/Paginator.php).

В штатном клиенте executor ведёт в `Pipeline::execute()`. Поэтому страницы проходят
тот же пайплайн, что и одиночный запрос. `Paginator` отвечает за изменение позиции,
условия продолжения, ограничения и сбор результатов. `ResponseHydrator` отвечает
за преобразование данных одной страницы и её элементов; он не запускает следующую страницу.

Режимы, метаданные и ошибки пагинации описаны в
[справочнике](../reference/execution/pagination.md).

## Composite и зависимости

[PipelineCompositeHandler](../../src/Pipeline/Flow/PipelineCompositeHandler.php)
выбирает ветку после валидации, до подготовки основного HTTP-запроса.
[CompositeFlow](../../src/Pipeline/Execution/CompositeFlow.php) связывает её с batch-исполнителями:

- `CompositeExecutor` отправляет `requests()` с ролью `Nested`. Затем
  `aggregate()` собирает значение из `ResultCollection`; при объявленном типе
  ответа оно гидратируется гидратором клиента. Результат содержит дочерние исполнения
  в `nested`. Отдельной HTTP-отправки агрегирующего запроса нет.
- `DependsOnExecutor` отправляет `dependencies()` с ролью `Dependency`.
  `processDependencies()` применяет полученные данные. Затем `CompositeFlow`
  повторно входит в Pipeline для основной операции с `skipComposite: true`
  и `skipValidation: true`, сохраняя родительский контекст.

Внутренние skip-флаги предотвращают повторный запуск зависимостей и валидации
при этом входе. `FailStrategy` определяет, можно ли продолжить после ошибок детей;
публичный контракт — в [композиции запросов](../reference/request/composition.md).

## Batch и pool

[BatchExecutor](../../src/Execution/BatchExecutor.php) преобразует допустимые элементы
в запросы и выбирает `SequentialBatchStrategy` или `ParallelBatchStrategy`.
Обе стратегии отправляют элементы через [BatchContext](../../src/Execution/Batch/BatchContext.php),
который учитывает клиента, роль и родительское исполнение. На batch также опираются
`CompositeExecutor` и `DependsOnExecutor`.

[PoolExecutor](../../src/Execution/PoolExecutor.php) организует очередь promise
через `sendAsync()` клиента, ограничивает concurrency и собирает `PoolResult`.
Настройки остановки, обработчиков и допустимых элементов принадлежат
[контракту batch/pool](../reference/execution/batch-pool.md).

## Promise API и режим провайдера

`SendMode` определяет форму отправки: `send()` возвращает handle с результатом,
`sendAsync()` — handle с promise. В штатном
[Pipeline](../../src/Pipeline/Pipeline.php) метод `executeAsync()` сразу вызывает
синхронный `execute()` и разрешает или отклоняет promise. При пагинации клиент
сначала выполняет обход страниц и оборачивает итог в promise.

Поэтому `SendMode::Async`, parallel batch и pool со штатным клиентом не гарантируют
параллельные HTTP-операции. Это свойство фактического пути исполнения;
[транспортный контракт](../reference/execution/transport.md) описан отдельно.

`ContinuationMode` управляет состоянием операции у провайдера. Его `Auto`, `Sync`
и `Async` не выбирают способ сетевого I/O в PHP.

## Ожидание continuation

[ContinuationService](../../src/Continuation/ContinuationService.php) получает клиент
и его гидратор. `ResultHandle::await()` передаёт сервису стартовый `ExecutionResult`,
исходный запрос и опции ожидания; также возможен вход по уже известному token.

Сервис строит `ContinuationContext` и выбирает resolver готовности. По режиму он
оценивает стартовый результат или сразу переходит к polling. Resolver возвращает
Pending, Ready или Failed; успешное создание DTO не используется как проверка готовности.
Poll-запросы отправляются через клиент и проходят обычный пайплайн.

После Ready сервис гидратирует выбранный payload, если задан финальный тип.
`ContinuationOutcome` сохраняет payload до гидратации, значение, последний результат
и число оценённых ответов. Handle сохраняет outcome: повторный `await()` возвращает
значение, а `awaitAs()` с другим типом использует тот же payload без нового polling.

Ошибка финальной гидратации завершает ожидание; она не превращается в Pending.
При изменении этой ветки сверяйте [критерий готовности](../reference/execution/continuation-state.md)
и [доставку ошибок и лимиты ожидания](../reference/execution/continuation-await.md).

## Контекст дочернего выполнения

`AbstractClient` реализует `ContextualClientInterface::sendInContext()`.
Batch с переданным `PipelineContext` использует этот контракт для передачи роли,
traceId и родительского бюджета в обоих режимах. Для composite/depends-on родитель
передаётся автоматически; основная отправка после dependencies использует остаток
того же бюджета. Дочерняя отправка сохраняет обработку пагинации и метаданных клиента.

Собственный клиент должен реализовать этот контракт, если получает дочерние запросы
с активным родительским deadline. Иначе `BatchContext` выдаёт ошибку конфигурации
до отправки. Независимый batch не получает общий таймаут на весь список автоматически.
См. [бюджет и deadline](../reference/execution/deadlines.md).

## Где проверять изменения

| Связь механизмов | Сценарии |
| --- | --- |
| Обёртки и выбор пагинации | [RequestExecutionChainTest](../../tests/Unit/Request/RequestExecutionChainTest.php), [RequestResolverTest](../../tests/Unit/Request/RequestResolverTest.php) |
| Агрегация и повторный вход | [CompositeFlowTest](../../tests/Unit/Execution/CompositeFlowTest.php), [PipelineSkipFlagsTest](../../tests/Unit/Pipeline/PipelineSkipFlagsTest.php) |
| Массовое выполнение | [BatchExecutorTest](../../tests/Unit/Execution/BatchExecutorTest.php), [PoolExecutorTest](../../tests/Unit/Execution/PoolExecutorTest.php) |
| Готовность и повторный await | [ContinuationReadinessTest](../../tests/Unit/Result/ContinuationReadinessTest.php), [ResultHandleContinuationAwaitTest](../../tests/Unit/Result/ResultHandleContinuationAwaitTest.php) |
| Общий бюджет | [ExternalDeadlineTest](../../tests/Unit/Timing/ExternalDeadlineTest.php) |
