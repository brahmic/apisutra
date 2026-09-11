# План работ (**ОТЛОЖЕНО**): реализация настоящего async (breaking)

- Дата создания: неизвестна (исторический материал)
- Дата обновления: 2026-09-11
- Дата переноса: 2026-09-11
- Статус: отложен

## Проблематика и статус

Исторический план сохранён после переноса из исходников. Полноценный async
отложен из-за объёма рефакторинга и риска изменения поведения SDK. Текущее
синхронное выполнение под Promise API принято владельцем; перенос документа
не возобновляет реализацию. См. [обсуждение](../discussion/dsc-001-async-first-analysis.md)
и [уточнение F04 в аудите](../audit/aud-001-architecture-code-2026-09-11/aud-001-readme.md#f04-документация-не-отражает-принятое-ограничение-async-api).

Ниже сохранён предложенный тогда вариант перехода. Его технологии, сроки
и изменения API требуют пересмотра перед возобновлением работы.

Цель: обеспечить **реальное** асинхронное выполнение (не блокирующее поток) для всех стадий пайплайна, включая retry/rate‑limit/хуки, и согласованную семантику ошибок.

---

## 0) Базовые решения (обязательные до кода)
1. **Выбор async‑runtime**  
   Рекомендация: ReactPHP (Loop + timers + HTTP client) с поддержкой `GuzzleHttp\Promise\PromiseInterface` через собственные адаптеры.  
   Альтернатива: Amp (корутины + Future) — потребует смены типа промиса по всему коду.

2. **Единая семантика промисов**  
   - Базовый контракт: `PromiseInterface<ExecutionResult>`.  
   - `throwOnErrors=false` → **resolve** с `ExecutionResult` (даже при ошибке).  
   - `throwOnErrors=true` → **reject** (или throw при wait).

3. **Breaking API‑решение**  
   - `send()` → асинхронный, возвращает `PromiseInterface<ExecutionResult>`.  
   - `sendSync()` → синхронный wrapper `send()->wait()`.  
   - `sendAsync()` → удалить или оставить как alias `send()` (решить заранее).

4. **Минимальный целевой контур**  
   Async должен быть реальным: **никаких `sleep/usleep`** на пути выполнения.

---

## 1) Контракты и типы (блок 1)
**Зависимости:** решения из пункта 0.

1. **TransportInterface (breaking)**
   - `sendAsync(PreparedRequest): PromiseInterface<ProviderResponse>` — основной метод.
   - `send(PreparedRequest): ProviderResponse` — либо удалить, либо сделать thin wrapper (`wait`).

2. **ClientInterface / AbstractClient**
   - `send(RequestInterface): PromiseInterface<ExecutionResult>`  
   - `sendSync(RequestInterface): ExecutionResult`  
   - `executeAsync()` становится основным в Pipeline.

3. **RequestInterface / AbstractRequest / RequestExecution**
   - `send()` возвращает promise.  
   - `sendSync()` как удобный sync wrapper.  
   - `dataOrFailSync()` при необходимости (опционально).

4. **ResultHandle**
   - Решить: оставить и сделать async‑first, либо убрать и заменить на прямые промисы.

**Артефакты:** обновлённые контракты, компилируемый код (пока без реализации async‑пайплайна).

---

## 2) Async‑ядро (блок 2)
**Зависимости:** контракты обновлены.

1. **AsyncScheduler / AsyncSleeper**
   - Интерфейс неблокирующей задержки `sleepMsAsync(int): PromiseInterface<void>`.
   - Реализация через ReactPHP Loop (или другой runtime).

2. **AsyncRateLimiter**
   - `acquireAsync()` вместо `sleep()` — ожидание через scheduler.

3. **AsyncDelayApplier**
   - Все задержки только через async‑sleep.

4. **AsyncRetryHandler**
   - `handleAsync(...)` возвращает promise, использует async‑delay.

**Артефакты:** базовые async‑примитивы без интеграции в pipeline.

---

## 3) Async‑Transport (блок 3)
**Зависимости:** AsyncScheduler готов.

1. **HttpTransportAsync**
   - Реализация `sendAsync()` на неблокирующем клиенте (React HTTP/Amp).  
   - `send()` → `sendAsync()->wait()` (если оставляем).

2. **MockTransportAsync**
   - Асинхронный mock для тестов (без `sleep`).

3. **RecordingTransport/Fixture**
   - Асинхронные варианты (или адаптеры).

**Артефакты:** полноценный async‑transport.

---

## 4) Async‑пайплайн (блок 4)
**Зависимости:** Async‑transport и async‑примитивы.

1. **PipelineAsync**
   - `executeAsync()` строит цепочку промисов.
   - `execute()` → `executeAsync()->wait()`.

2. **RequestFlowRunnerAsync**
   - Все стадии (`BeforeSend → AfterResponse → BeforeHydrate → AfterHydrate`) в promise‑цепочке.
   - Любые исключения → в `ExecutionResult` или reject (по `throwOnErrors`).

3. **RetrySenderAsync**
   - Retry/RateLimit/Delay/Hook в async‑цепочке.

4. **HookRunnerAsync**
   - Разрешить асинхронные хуки (HookInterface может возвращать `PromiseInterface|void`).

**Артефакты:** async‑пайплайн, синхронная обёртка сохранена.

---

## 5) Async‑пагинация (блок 5)
**Зависимости:** async‑пайплайн.

1. **AsyncPaginator**
   - `allAsync()/pagesAsync()/rangeAsync()` → Promise<PaginatedResult>.
   - Cursor‑based: строго последовательная цепочка.
   - Offset‑based: опционально параллелить страницы через PoolExecutorAsync.

2. **RequestResolver**
   - В async‑режиме выбирает AsyncPaginator.

**Артефакты:** реальный async для пагинации (не блокирует поток).

---

## 6) Экзекьюторы и конкурентность (блок 6)
**Зависимости:** async‑пайплайн.

1. **BatchExecutorAsync / PoolExecutorAsync**
   - Работают на промисах, без `wait()` внутри.
   - `stopOnFailure` управляет очередью/планировщиком.

2. **ResultCollection/PoolResult**
   - Без изменений, но обновить места сборки.

---

## 7) Тесты и инструментирование (блок 7)
**Зависимости:** async‑пайплайн готов.

1. **Новые тест‑помощники**
   - Async‑scheduler фейк  
   - Async‑transport mock

2. **Переписать тесты**
   - Retry/RateLimit/Hook/Pipeline — под промисы  
   - Pagination — async‑цепочки  
   - Batch/Pool — async‑результаты

3. **E2E‑тест**
   - Полный flow async: request → pipeline → retry → pagination.

---

## 8) Документация и миграция (блок 8)
1. Обновить docs: async‑семантика, ошибки, примеры.  
2. Миграционный гайд:
   - `send()` теперь async
   - `sendSync()` для синхронного кода
   - отличия поведения `throwOnErrors`.

---

## 9) План внедрения (без коллизий)
1. **Сначала контракты** → чтобы всё зависимое видно было сразу.  
2. **Затем async‑примитивы** (sleep/rate‑limit/retry).  
3. **Потом transport** → источники I/O.  
4. **Далее pipeline** → центральный поток.  
5. **Пагинация** → использует pipeline.  
6. **Batch/Pool** → поверх pipeline.  
7. **Тесты + docs** → финальная стабилизация.

---

## Критерии успеха
- `send()`/`execute()` не блокируют поток при async‑вызове.  
- Никаких `sleep/usleep` в async‑пути.  
- Для cursor‑pagination — последовательный, но неблокирующий pipeline.  
- Для offset‑pagination — опционально параллелизуемый.  
- Полная совместимость с `throwOnErrors` в sync/async.

## Выводы

План остаётся в backlog. Принятый сейчас объём — сохранение текущей семантики
выполнения и устранение противоречий в её документации. Реализация описанного
async-first перехода требует отдельного решения об объёме и совместимости.
