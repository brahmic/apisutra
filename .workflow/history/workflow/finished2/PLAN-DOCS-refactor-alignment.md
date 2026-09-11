# План: синхронизация документации после рефакторинга

## Цель
Привести документацию в соответствие с модульной архитектурой и новым неймспейсингом enum‑ов.

## Контекст
Рефакторинг разнёс enum‑ы по неймспейсам и разбил Pipeline на модули.

## Что обновить
1) `docs/architecture/06-config.md`
   - Добавить `CacheConfig.enabled`.
   - Указать enum‑классы с namespace.
2) `docs/architecture/03-pipeline.md`
   - Описать модульную структуру Pipeline и роли компонентов.
   - Уточнить `PipelineStage` с `HttpRequest/HttpResponse`.
3) `docs/architecture/07-attributes.md`
   - Добавить `AttributeContextType`.
4) `docs/features/*`
   - `features/files.md`: namespace для `FileFormat`.
   - `features/hooks.md`: namespace для `Hook`, `HookPriority`.
   - `features/logging.md`: namespace для `PipelineStage`, `RequestRole`.
   - `features/laravel-integration.md`: таблица Environment + AttributeMetadataCache.
5) `docs/glossary.md`
   - Обновить enum‑термины с namespace.
   - Добавить `AttributeContextType`.
   - Уточнить `Pool` (фиксированная concurrency).
6) `docs/architecture/01-contracts.md`
   - Проверить `RetryHandlerInterface` на совпадение с кодом.

## Критерии приёмки
- Документы отражают текущую структуру кода.
- Примеры с корректными `use`/namespace.
- Нет расхождений между architecture/features/glossary.

## Риски
- Большой объём правок → риск пропустить раздел.  
  Решение: пройтись по списку файлов и отметить в checklist.
