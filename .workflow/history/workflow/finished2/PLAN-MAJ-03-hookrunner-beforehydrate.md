# План: корректная обработка BeforeHydrate

## Цель
Сделать `BeforeHydrate` единообразным: результат массива должен применяться к данным, а не игнорироваться.

## Контекст
Файл: `packages/brahmic/apisutra/src/Pipeline/Hooks/HookRunner.php`

## Проблема
В `runHookStage()` ветка `BeforeHydrate` не использует возвращаемый массив (бесполезное присваивание `$context->response = $context->response;`).

## Шаги
1) Удалить ветку `BeforeHydrate` из `runHookStage()` либо сделать её приватной.
2) В `Pipeline` использовать только `runBeforeHydrate()` для стадии `BeforeHydrate`.
3) Проверить, что `BeforeHydrate` отрабатывает для:
   - хуков из `HookRegistry`
   - атрибутов `#[BeforeHydrate]`
   - метода `AbstractRequest::beforeHydrate()`

## Критерии приёмки
- Массив из `beforeHydrate()` реально применяется к данным.
- Нет двойного выполнения `BeforeHydrate`.

## Риски
- Изменение порядка хуков может повлиять на кастомную логику.  
  Решение: сохранить текущий порядок обработки.
