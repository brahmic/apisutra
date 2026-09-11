# Повторный аудит пакета brahmic/apisutra
Дата: 2026-02-05  
Основание: повторная проверка после исправлений  
Объект: `packages/brahmic/apisutra`

## Итоговая оценка готовности
- Релиз SDK: **needs work**
- Создание первого провайдера: **частично готово, но с рисками** (см. замечания)

## Исправлено по сравнению с прошлым аудитом
1. Импорт `RequestRole` в `AbstractRequest::isRoot()` добавлен.  
   Файл: `src/Core/AbstractRequest.php`.
2. Валидация `ClientConfig` появилась (baseUrl/timeout/connectTimeout/delay/authRetryAttempts/idempotencyHeader).  
   Файл: `src/Config/ClientConfig.php`.
3. Добавлен hard‑limit пагинации через `PaginationConfig.maxPages`.  
   Файлы: `src/Config/PaginationConfig.php`, `src/Pagination/Paginator.php`.
4. `Pipeline::execute()` полностью обёрнут в try/catch (включая ранние стадии).  
   Исключения до `runStages()` превращаются в `ExecutionResult` при `throwOnErrors=false`.  
   Файл: `src/Pipeline/Pipeline.php`.
5. Существенно расширены unit‑тесты по discovery/pipeline/execution/retry/pagination/files/async.

## Оставшиеся блокеры релиза
1. **`composer.json` — `minimum-stability: dev`**  
   Файл: `composer.json`.  
   Для релиза SDK требуется стабильная политика зависимостей.

## Замечания, критичные для первого провайдера
1. **Документация не покрывает ключевые термины и стартовый путь**  
   Отсутствуют: `RequestOptionsProviderInterface`, `PaginationConfig`, `getting-started`.  
   Риск: неправильная интеграция и непонимание правил runtime‑опций/пагинации.

## Тесты и бизнес‑сценарии (итог)
Покрытие заметно улучшено, но остаются пробелы:
- Discovery: ошибки и edge‑cases сканирования (classmap/PSR‑4, отсутствие autoload).
- Pipeline: полноформатный интеграционный flow стадий (в одной цепочке успех + ошибки/skip‑флаги).
- Retry: jitter, retryExceptions, общий total‑timeout (если планируется как опция).
- Results/Errors: errorMapper и агрегация nested‑ошибок.

## Рекомендации перед релизом
1. Перевести `minimum-stability` на `stable` (если это релизный пакет).
2. Добавить минимальный `getting-started` для первого провайдера.

---
Вывод: пакет сильно приблизился к релизу, но сохраняется 1 технический блокер и 1–2 документационных риска для старта первого провайдера.
