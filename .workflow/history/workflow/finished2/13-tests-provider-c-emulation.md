# План 13: тесты Provider C (async + uuid в URL) (P0)

## Проблема
Нет тестов, покрывающих поставщика типа Provider C, где сначала выполняется системный запрос и возвращается `uuid`, а затем выполняются асинхронные запросы на отчётные эндпоинты с подстановкой `uuid` в URL, с возможными ответами «ждите» и с пагинацией.

## Рекомендованное решение
Сделать полноценную эмуляцию Provider C по аналогии с Provider A/B: стабы запросов, DTO, маппинг статусов, фикстуры и unit‑тесты, которые проверяют:
1) подстановку `uuid` в path и `token` в query;
2) последовательность синхронный → асинхронный запрос;
3) обработку статусов/ожидания;
4) пагинацию и разные параметры;
5) download‑отчёты и json‑отчёты;
6) корректное наполнение DTO и маппинги.

## Сценарии (перечень юзкейсов)
1) **Системный запрос**: `/people-check.json` (или `/org-check.json`) — возвращает `uuid` и `status`. Проверить DTO и маппинг статуса.
2) **Асинхронный отчёт**: `/report/{uuid}/org-judge.json?event=role-data` — сначала «ждите» (waitTime/status), затем готовые данные.
3) **Пагинация**: `page/rows` + meta из `response.count/page/rows` → корректный `PaginationMeta`.
4) **Разные параметры**: `event`, `filter0`, `filter_text`, `strategy` — сериализация в query.
5) **Download‑отчёт**: `/report/{uuid}/report.{format}?report_name=...` для `docx/pdf/html` → `#[Download]` и `FileResponse`.
6) **JSON‑отчёт**: тот же эндпоинт с `format=json` → обычный DTO/array.
7) **Статусы ошибок**: отрицательные `status` (тариф/баланс/ограничения) → `ResultStatus::FAILED`.
8) **Подстановка `uuid`**: после первого запроса все последующие используют тот же `uuid` в path.

## План работ
1) **Стабы запросов Provider C**
   - `ProviderCSystemPeopleCheckRequest` (`/people-check.json`)
   - `ProviderCSystemOrgCheckRequest` (`/org-check.json`)
   - `ProviderCReportJudgeRoleDataRequest` (`/report/{uuid}/org-judge.json`)
   - `ProviderCReportDownloadRequest` (`/report/{uuid}/report.{format}`)
   - Общие query‑поля: `token`, `event`, `page`, `rows`, `report-name`, `timeout`

2) **DTO и маппинг статусов**
   - `ProviderCSystemResponseDto` (status, query_type, uuid)
   - `ProviderCAsyncResponseDto` (status, waitTime, response)
   - `ProviderCStatusMap` (status → ResultStatus)

3) **Пагинация**
   - `#[Pagination(pageParam: 'page', limitParam: 'rows', metaPath: 'response')]`
   - `extractMeta` при необходимости: `count/page/rows`

4) **Фикстуры**
   - Системный запрос (ok)
   - Асинхронный ответ «wait»
   - Асинхронный ответ «ready» с paginated `response.result`
   - Download (binary content + headers)
   - JSON‑отчёт (format=json)

5) **Unit‑тесты**
   - Гидрация DTO (system/async)
   - Маппинг статусов
   - Подстановка `uuid` и `token` в URL/query
   - Асинхронная последовательность (wait → ready)
   - Пагинация (page/rows, meta, next page)
   - Download vs json отчёты

## Нюансы
- В query есть имена с точками и дефисами (`PeopleQuery.FirstName`, `report-name`) — использовать `#[Query(name: ...)]`.
- `uuid` всегда в path, `token` всегда в query — обязателен тест на формирование URL.
- Формат `report.{format}` требует подстановки формата в path.
- Для download запросов кеш по умолчанию запрещён (текущее правило пакета).
- Для polling использовать `withoutCache()` чтобы исключить кеш между вызовами.
