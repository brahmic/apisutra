# План: глубокая декомпозиция Pipeline

## Цель
Сделать `Pipeline` тонким оркестратором, вынести этапы в отдельные компоненты без изменения бизнес‑логики.

## Принципы
- Поведение и порядок стадий сохраняются строго.
- Нет изменения публичного API.
- Вся логика разнесена по шагам/сервисам.

## Декомпозиция
1) `PipelineContextFactory`
   - Создание `PipelineContext`
   - Инициализация `traceId`
   - Старт пайплайна (audit, Stage::Started, лог)

2) `PipelineValidator`
   - Валидация запроса
   - Формирование failed‑результата при ошибках
   - Учет `throwOnErrors`

3) `PipelineCompositeHandler`
   - Выполнение composite/depends‑on
   - Учет `skipComposite`

4) `RequestPreparationStep`
   - Сериализация + overrides
   - Audit/log prepared request

5) `RequestFlowRunner`
   - BeforeSend stage (атрибуты → auth → hooks)
   - Cache read / transport / cache hit
   - Error check + throwOnErrors
   - BeforeHydrate → Hydration → AfterHydrate
   - Success finalize (cache store, debug, meta, audit)

6) `ExecutionResultBuilder`
   - EarlyReturnResult
   - ExceptionResult
   - Success/Failed helpers

## Нюансы
- `throwOnErrors` сохраняется валидации/ошибок
- `skipValidation` / `skipComposite` работают как раньше
- Audit события не меняются
- Cache write только после успешной гидрации
- Debug формируется идентично

## Шаги реализации
1. Создать новые классы в `src/Pipeline/Flow/`.
2. Перенести логику из `Pipeline` в новые классы без изменения поведения.
3. Переподключить `Pipeline` как оркестратор.
4. Удалить старые приватные методы и неиспользуемые импорты.
5. Проверить отсутствие логических изменений.

## Критерии приёмки
- `Pipeline` стал тонким оркестратором.
- Все этапы выполняются в прежнем порядке.
- Поведение и публичные сигнатуры не изменены.
