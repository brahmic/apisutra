# Реализация и приёмка 030

- Дата: 2026-09-14
- Статус: завершено
- Принятые контракты: `104f98e`
- Baseline реализации: `4d50f78`, после исправления 031 (`0950b77`)
- Код и публичная документация: `810933c`

Реализован [контракт](contracts.md) без legacy-эвристики. Resolver определяет
Pending/Ready/Failed до преобразования финала; ошибочные Ready-данные немедленно
завершают ожидание. Один гидратор клиента обслуживает все входы, включая смену
типа сохранённого outcome. HTTP-ответ, attempts и исходное исключение сохраняются;
автоматический лог получает контекст без payload и token.

## Матрица приёмки

Все строки [C01–C33](acceptance.md) выполнены. Основные регрессии находятся в
[ContinuationReadinessTest](../../../tests/Unit/Result/ContinuationReadinessTest.php),
прежние сценарии — в
[ResultHandleContinuationAwaitTest](../../../tests/Unit/Result/ResultHandleContinuationAwaitTest.php).

| Строки | Проверка |
| --- | --- |
| C01–C04 | Sync/Auto Ready без poll; invalid field с полным путём; Sync Pending |
| C05–C09 | Несколько Pending; Async пропускает старт; ошибка Ready прекращает poll с token и без него; defaults не означают готовность |
| C10–C18 | Порядок resolver; отсутствие критерия/extractor/token; Failed с token; исключения resolver/provider; оба token-входа |
| C19–C20 | Нетипизированный финал, включая null; scalar Ready одинаково оборачивается при первом и повторном преобразовании |
| C21–C23 | Тождественность кешированного DTO; смена типа из исходного payload; ошибка сохраняет прежний outcome |
| C24 | Sync, async, вложенные sync/async handles, composite sync/async, pool; публичный metadata cache клиента содержит metadata финального DTO |
| C25 | Обязательный Hydrator в конструкторе сервиса; сторонний ClientInterface с собственным кешем и явным default() |
| C26 | Два ожидания не разделяют defaults и объектные Cast args в Production/Testing |
| C27–C29 | Структурированный автоматический лог без debug; исходный HTTP-ответ; result-first/throwOnErrors; прежние тесты continuation |
| C30 | Публичные руководства, миграция и changelog; опубликованные resolver/extractor выполняются в probe |
| C31–C33 | maxAttempts считает poll-запросы; attempts считает оценки; шесть проверок контекста; необъявленный await без resolver отклоняется |

Pool возвращает ExecutionResult, а не ResultHandle: его callback вызывает сервис
того же клиента; внутри pool покрыта ветка sendAsync/rawAsync. В C31 намеренно
используется `intervalMs: 5000`: при правильном завершении лимита пауза не выполняется.
Остальные polling-регрессии задают нулевой интервал.

## Результаты

| Команда | Результат и артефакт |
| --- | --- |
| Целевые continuation/composite тесты | [66 passed / 221 assertions](artifacts/implementation-focused-tests.log) |
| `composer test` | [1790 passed / 6625 assertions; 17 Redis skipped](artifacts/implementation-tests.log) |
| `composer lint` | [0 errors / 135 предупреждений длины строк](artifacts/implementation-lint.log) |
| `composer analyse` | [Без ошибок](artifacts/implementation-analyse.log) |
| `composer check-docs` | [89 документов](artifacts/implementation-public-docs.log) |
| `composer dump-autoload --optimize --strict-psr` | [Успешно](artifacts/implementation-autoload.log) |
| Проверка Git/Composer dist по index реализации | [Совпадают; standalone smoke без dev-зависимостей прошли](artifacts/implementation-package.json) |
| Примеры из руководств | [7/7](artifacts/implementation-doc-examples.json), [исполняемый probe](artifacts/implementation-doc-examples.php) |
| Ссылки workflow | [23 документа, ссылки и якоря без ошибок](artifacts/implementation-workflow-docs.json) |

При параллельном запуске общих проверок один существующий тест HTTP-таймаута
не получил ожидаемую TimeoutException. [Неуспешный прогон сохранён](artifacts/implementation-tests-timeout-retry.log).
Затем [отдельный файл: 6/22](artifacts/implementation-timeout-check.log) и полный
последовательный прогон прошли. Код транспорта и тест таймаута не менялись.

[Состояние реализации и SHA-256](artifacts/implementation-state.json) фиксирует код
и команды. [Baseline](artifacts/implementation-baseline-state.json) сохранён до
реализации; исторические probe дискуссии не переписаны.

## Граница следующего плана

030 не добавляет внешних правил DTO. [028](../pln-028-declarative-dto/pln-028-readme.md)
может передать набор в уже используемые гидратор и сериализатор клиента. Исправление
031 сохранено; повторная замена конструкторов continuation не требуется.
