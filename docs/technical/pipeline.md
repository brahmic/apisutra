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
Гидратор клиента и его [внешние правила](../guides/hydration-rules.md) используются в
Returns, пагинации, CompositeFlow и ожидании, включая повторный `awaitAs()`.
HTTP cache хранит ответ, который гидратируется заново. Ненулевой результат response
handler, RawResponse и Download обходят DTO-гидратацию.

Готовность continuation определяется до преобразования Ready-payload. Ошибка этого
преобразования завершает ожидание; [контракт ожидания](../guides/provider-async-await.md)
не зависит от включения внешних правил. Casts/providers получают текущий гидратор
через HydrationScope; `PipelineContext` standalone может отсутствовать.

- Пайплайн и контекст: `docs/glossary/pipeline.md`
- Результаты и ошибки: `docs/glossary/results.md`
- Атрибуты: `docs/guides/attributes/README.md`
- Логирование: `docs/guides/logging.md`
- Хуки: `docs/guides/hooks.md`
- Composite/DependsOn: `docs/guides/request-pipeline.md`
