# План: порядок валидации для Composite/DependsOn

## Цель
Избежать выполнения зависимостей при невалидном основном запросе и исключить двойную валидацию.

## Контекст
Файлы:  
- `packages/brahmic/apisutra/src/Pipeline/Pipeline.php`  
- `packages/brahmic/apisutra/src/Pipeline/Execution/CompositeFlow.php`

## Проблема
Composite/DependsOn обрабатываются **до** валидации, а затем основной запрос валидируется повторно при `execute()` после `processDependencies()`.

## Варианты решения
1) **Валидация до composite‑ветки** в `Pipeline::execute()`  
   - Если невалиден — вернуть failure и не запускать зависимости.
2) **Валидация внутри CompositeFlow**  
   - Выполнить один раз, сохранить результат, при ошибке вернуть `ExecutionResult`.
   - При вызове `$executor->execute()` передавать флаг `skipValidation`.

## Предпочтительно
Вариант 1, чтобы не усложнять `PipelineExecutorInterface`.

## Шаги
1) Переместить `validateRequest()` **до** `executeCompositeIfNeeded()`.
2) Убедиться, что composite‑ветка получает валидный запрос.
3) Если нужен `skipValidation`, добавить параметр с дефолтом, не ломая интерфейс.

## Критерии приёмки
- Зависимости не выполняются при невалидном запросе.
- Основной запрос валидируется ровно один раз.

## Риски
- Изменение порядка этапов может затронуть audit/logging.  
  Решение: сохранить `PipelineStage::Started` до валидации.
