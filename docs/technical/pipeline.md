# Pipeline (обзор)

Короткое описание пайплайна выполнения запроса. Детальные определения — в глоссарии.

## Идея
Pipeline — единый оркестратор жизненного цикла запроса: от валидации и подготовки до транспорта, гидрации и результата.

## Основные этапы (высокий уровень)
1) Валидация запроса  
2) Обработка composite/dependsOn  
3) Подготовка `PreparedRequest`  
4) Auth / Cache / Retry / Rate‑limit  
5) HTTP‑транспорт  
6) Гидрация (DTO/массив)  
7) Сбор ExecutionResult  

## Контекст
`PipelineContext` переносит request, config, traceId, role и рабочие данные (preparedRequest/response/dto).  
Роль запроса задаётся `RequestRole` (Root/Nested/Dependency).

## Хуки
Хуки разделены по стадиям: `BeforeSend`, `AfterResponse`, `BeforeHydrate`, `AfterHydrate`.  
Порядок исполнения: глобальные → по типу → атрибуты → методы класса.

## Где подробности
- Пайплайн и контекст: `docs/glossary/pipeline.md`
- Результаты и ошибки: `docs/glossary/results.md`
- Атрибуты: `docs/guides/attributes/README.md`
- Логирование: `docs/guides/logging.md`
- Хуки: `docs/guides/hooks.md`
- Composite/DependsOn: `docs/guides/request-pipeline.md`
