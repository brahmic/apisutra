# Приёмка готовности continuation и ошибок await

- Дата создания: 2026-09-14
- Дата обновления: 2026-09-14

Контракт — [contracts.md](contracts.md). Сейчас ни одна строка не выполнена.
Исходные воспроизведения на `eeb0b6a`: [review-probe](../../discussion/dsc-005-declarative-dto-contracts/artifacts/review-probe.json),
[followup-probe](../../discussion/dsc-005-declarative-dto-contracts/artifacts/followup-probe.json),
[clarification-probe](../../discussion/dsc-005-declarative-dto-contracts/artifacts/clarification-probe.json).
Их результаты не переписываются; baseline реализации сохраняется в `artifacts/` этого плана
при начале работ.

Все проверки используют MockTransport, `intervalMs: 0` и счётчик запросов.
Финальный DTO проверяется в двух вариантах: plain readonly-класс и существующий
`ContinuationFinalDto`.

## Готовность и финал

| ID | Сценарий | Ожидаемый результат |
| --- | --- | --- |
| C01 | Sync, `unwrap: data`, финал корректен | DTO; poll request не отправляется |
| C02 | Sync, `data.value = []` | `ContinuationAwaitException(final_hydration_failed)`, previous — `HydrationException` с path `data.value`; poll не отправляется |
| C03 | Sync, `data` отсутствует | `final_not_ready`, `attempts = 1` |
| C04 | Auto, стартовый ответ содержит `data` | DTO без polling |
| C05 | Auto, старт без `data` с token → poll без `data` → poll с `data` | DTO; ровно 2 poll-запроса |
| C06 | Async | Старт не оценивается; polling до Ready |
| C07 | Ready с неверным типом, token есть | Немедленно `final_hydration_failed`; следующий poll не отправляется |
| C08 | Ready с неверным типом, token нет | Та же ошибка |
| C09 | DTO со всеми defaults: промежуточный ответ без `data` и с `data: null` | Pending; ожидание продолжается |
| C10 | Декларация без `unwrap`, без `stateResolver`, без resolver клиента | `ContinuationConfigurationException` до первого poll |
| C11 | Pending до `maxAttempts` | `attempts_exhausted`; `attempts` и `lastResult` соответствуют последней попытке; лишних запросов нет |
| C12 | Pending без token, результат не failed | `continuation_token_missing` |
| C13 | Pending без token, результат failed | Исключение результата через `throw()` без обёртки |
| C14 | Failed provider-ответ с token, встроенный resolver | Pending, polling продолжается (прежний тест failed-pending) |
| C15 | Собственный resolver возвращает Failed при наличии token | Терминальная причина: `throw()` для failed-результата либо `continuation_failed` |
| C16 | Resolver бросает исключение | Исключение выходит без обёртки и не считается Pending |
| C17 | `stateResolver` в атрибуте, resolver клиента, `unwrap` одновременно | Порядок выбора из contracts.md |
| C18 | `awaitByToken` с декларацией; `awaitByTokenAs` с resolver клиента и без него | DTO; без resolver — configuration-ошибка |
| C19 | Запрос без `ContinuationResult`, resolver клиента, `await()` без типа | Resolver получает контекст с `finalType = null`, `unwrap = null`; возвращается payload без гидратации |
| C20 | Ready-payload scalar при `finalType` DTO, в ожидании и в `hydrateOutcome()` | `ContinuationAwaitException(final_hydration_failed)`; previous — `HydrationException(unexpected_response_shape)` с путём `path` или `$`; гидратор не вызывается |

## Объекты ожидания и гидратор

| ID | Сценарий | Ожидаемый результат |
| --- | --- | --- |
| C21 | Повторный `await()` и `awaitAs()` того же типа | Кешированное значение; запросов нет |
| C22 | `awaitAs()` другого типа после `await()` | Гидратация сохранённого payload; запросов нет; конструктор нового DTO один раз |
| C23 | `awaitAs()` другого типа с неверными данными | `final_hydration_failed` с previous (путь с префиксом `path`), исходными `lastResult` и `attempts`; кешированный outcome не изменён |
| C24 | Все ветки создания `ResultHandle` в AbstractClient: sync, async, promise, pool, composite | Ожидание использует `ContinuationService` клиента с его гидратором |
| C25 | `ContinuationService` без гидратора; сторонний `ClientInterface` с явным `Hydrator::default()` | Сигнатура требует гидратор; явная передача работает |
| C26 | После 031, cache on/off, повторные ожидания | Изоляция defaults и атрибутных args сохранена |

## Диагностика и совместимость остального

| ID | Сценарий | Ожидаемый результат |
| --- | --- | --- |
| C27 | `context()` и автоматический лог при `debug=false` | reason, attempts, httpStatus, traceId, hydration; без payload и token |
| C28 | Стартовый запрос: result-first, `throwOnErrors`, HTTP-ответ | Прежнее поведение `send()`/`raw()` |
| C29 | Существующие тесты continuation | Переписаны под объявленный `unwrap`/resolver; прежний тест immediate final использует ответ с `data` |
| C30 | Документация и changelog | Миграция и все reason описаны; примеры выполняются |
| C31 | `maxAttempts: 1`: Auto со стартом Pending и Pending poll; Async с Pending poll | Один poll-запрос в обоих случаях; `attempts` равен 2 и 1; паузы после последнего запроса нет |
| C32 | Контекст, переданный resolver, для всех строк таблицы «Контекст ожидания» | Поля `finalType`, `unwrap`, `sourceRequestClass`, `mode` совпадают с таблицей |
| C33 | Запрос без `ContinuationResult` и без resolver клиента | `ContinuationConfigurationException` до первого poll |

## Команды и завершение

```bash
vendor/bin/pest tests/Unit/Result/ResultHandleContinuationAwaitTest.php tests/Unit/Execution/CompositeFlowTest.php --compact
composer test
composer lint
composer analyse
composer check-docs
```

План завершается после прохождения C01–C33, завершения 031 для C24–C26 и сохранения
результатов с commit в `artifacts/`.
