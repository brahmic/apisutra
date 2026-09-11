# Логирование, Audit и TraceId

ApiSutra даёт два уровня наблюдаемости:
- **Audit‑лог** — структурированный журнал этапов pipeline
- **Логи** через PSR‑3 — сообщения о ключевых событиях

## TraceId
TraceId связывает все этапы выполнения запроса и используется:
- в audit‑событиях
- в логах (как `trace`)
- в `ExecutionResult::traceId`

### Приоритет источников traceId
1) `withTraceId()` на конкретном запросе  
2) `withTraceId()` в runtime‑опциях (через RequestExecution)  
3) traceId, заданный на клиенте (`$client->setTraceId()`)  
4) сгенерированный UUID

## Audit‑лог
Audit хранится в `ExecutionResult::$audit` как массив `PipelineEvent`:
- stage (`PipelineStage`)
- timestamp + duration
- requestClass
- role (`RequestRole`)
- payload (только при debug)

## Debug‑payload
При `ClientConfig::debug = true` в audit‑событиях и в `ExecutionResult::debug`
появляется `DebugInfo`:
- `preparedRequest` (метод/URL/заголовки/тело)
- `response` (status/headers/body)
- `duration`
- `nested` (для batch/pool)

**Нюанс:** debug‑payload может содержать большие тела ответов.

## Логи (PSR‑3)
Если в `ClientConfig` задан `logger`, SDK пишет события:
- завершение запроса
- ошибки валидации
- исключения
- повторные запросы (retry)

Минимальный уровень настраивается через `logLevel` (PSR‑3).

## Где ещё смотреть
- `docs/guides/client-config/observability.md` — debug и environment
- `docs/technical/pipeline.md` — обзор пайплайна
