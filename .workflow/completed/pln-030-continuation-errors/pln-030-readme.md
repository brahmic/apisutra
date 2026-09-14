# Готовность continuation-результата и сохранение ошибок await

- Дата создания: 2026-09-14
- Дата обновления: 2026-09-14
- Статус: завершено

## Основание и цель

[Перепроверка await в dsc-005](../../discussion/dsc-005-declarative-dto-contracts/peer-review.md#pr-01-входы-await-и-доставка-ошибок)
воспроизвела потерю HydrationException в существующем API. Повторный
[разбор границ планов](../../discussion/dsc-005-declarative-dto-contracts/followup-review.md)
подтвердил её на plain readonly DTO без атрибутов и интерфейсов пакета.
Исправление выделено из 028: внешний набор правил для воспроизведения не нужен.

Цель — различать ожидающий ответ и неверные финальные данные, сохранять исходную
диагностику и доступ к ответу, на котором завершилось ожидание. SDK задаёт свой
критерий готовности, а пакет исполняет общий цикл continuation. Изменение не должно
требовать Laravel или знания протокола конкретного провайдера в ядре.

## Реализация завершена

Код и публичная документация — `810933c`; [результаты приёмки](implementation.md).
C01–C33 выполнены, включая передачу гидратора после 031.

## Принятое основание реализации

Контракт принят владельцем пакета 2026-09-14: [contracts.md](contracts.md),
матрица — [acceptance.md](acceptance.md), основания — [ADR-001](../../adr/adr-001-continuation-readiness.md).
Выбран архитектурно самый чистый вариант без сохранения старой эвристики.
План готов к реализации этапов 2–4. Подключение гидратора клиента к await (C24–C26)
выполняется после завершения [031](../pln-031-metadata-value-isolation/pln-031-readme.md);
остальная работа от 031 не зависит.

Этап 2 начат после завершения 031, на коммите `4d50f78`.
[Baseline реализации](artifacts/implementation-baseline-state.json) и
[исходные continuation/composite тесты](artifacts/implementation-baseline-tests.log)
сохранены до изменения кода.

## Подтверждённое поведение

База — `eeb0b6a6154f8f95799be08f1389b799b19a6c24`, PHP 8.4.15.

| Вызов на неверных данных финального DTO | Текущий результат |
| --- | --- |
| Прямой Hydrator::hydrate | HydrationException с reason/path |
| Continuation Sync | Ошибка сборки финала как ContinuationConfigurationException без исходной ошибки |
| Polling с сохраняющимся token | Повторные попытки, затем configuration-ошибка лимита |
| Polling без следующего token | Configuration-ошибка отсутствия финала/token |
| Cached awaitAs | ConfigurationException с текстом исходной ошибки, но без её структуры и previous |

[ContinuationService](../../../src/Continuation/ContinuationService.php) считает
любое исключение внутри попытки гидратации признаком resolved=false.
[ResultHandle](../../../src/Result/ResultHandle.php) отдельно заменяет ошибку
повторного преобразования. Наличие token само по себе не отличает pending от ready.
Успех гидратации также не является надёжным признаком готовности: DTO с defaults
успешно создаётся из промежуточного ответа. Это воспроизведено через публичный
Auto/awaitFromStartResult: без unwrap, с отсутствующим data и с data=null при
unwrap=data. Во всех трёх случаях возвращён DTO с defaults без HTTP-запросов.
applyUnwrap использует `unwrapped ?? data`, поэтому отсутствие/null возвращают
весь payload.

Дополнительная находка CR-01 оказалась общей для гидратора и сериализаторов:
metadata cache удерживает объекты из constructor defaults и аргументов атрибутов.
После [расширенной проверки](../../discussion/dsc-005-declarative-dto-contracts/metadata-review.md)
это отдельный дефект P1 и [план 031](../pln-031-metadata-value-isolation/pln-031-readme.md).

## Принятые решения

| Вопрос | Решение (подробно — contracts.md) |
| --- | --- |
| Откуда берётся готовность | `ContinuationStateResolverInterface` по результату и `ContinuationContext` (финальный тип, путь, исходный запрос, режим) возвращает Pending, Ready(payload) или Failed; гидратация не участвует |
| Простая обёртка | `ContinuationResult::unwrap` — путь финала для встроенного `FinalPathStateResolver`: присутствует и не null — Ready, иначе Pending; запасного корня нет |
| Плоский ответ | Собственный resolver SDK в атрибуте или `ClientConfig::continuationStateResolver` |
| Нет критерия | `ContinuationConfigurationException` до первого poll request |
| Старая эвристика | Удаляется без legacy-режима; ломающее изменение описывается в миграции |
| Ошибка Ready | Любая ошибка преобразования, включая неверную форму payload, — `ContinuationAwaitException(final_hydration_failed)` с previous `HydrationException`, без следующих попыток |
| Прочие runtime-исходы | `final_not_ready`, `continuation_token_missing`, `attempts_exhausted`, `continuation_failed` с `attempts` и `lastResult` |
| Ошибочный provider-ответ | Failed-результат без продолжения бросает своё исключение; терминальность при наличии token задаёт resolver |
| Повторный awaitAs | Кешируется `ContinuationOutcome`; другой тип гидратируется из сохранённого payload без запросов |
| Передача гидратора | Обязательный аргумент `ContinuationService`; `ResultHandle` не гидратирует сам |

## Объём и границы с 028 и 031

В 030 входят:

- определение Pending/Ready независимо от гидратации финального DTO;
- доставка ошибок Sync, Auto/Async polling и cached awaitAs;
- сохранение reason/path/previous и последнего ExecutionResult/HTTP-ответа;
- удаление старой эвристики и fallback unwrap к корню, миграция;
- передача гидратора клиента в ContinuationService и перенос гидратации cached awaitAs;
- регрессии и документация continuation/await и ошибок.

В [028](../../current/pln-028-declarative-dto/pln-028-readme.md) остаются HydrationRules, strict,
extras, sourcePath и подключение правил к одному гидратору клиента. Continuation
получает этот экземпляр уже в 030; повторно менять конструкторы в 028 не требуется.
030 не вводит внешние правила и не меняет scalar-политики.

Общий дефект значений metadata исправляется целиком в 031. В 030 остаётся регрессия
изоляции при передаче исправленного исполнителя в await; дублировать реализацию
metadata здесь нельзя.

Зависимость направленная: 030 не ждёт 028. Интеграция правил 028 в await ждёт
завершения 030. Передача кеширующего гидратора и итоговая приёмка 030 ждут
реализации 031. План 027 остаётся отдельной последующей работой по документации.

## Этапы

### 1. Контракт и приёмка

- [x] Зафиксировать границу с CR-01/031 в contracts.md и acceptance.md.
- [x] Создать contracts.md с состояниями, сигнатурами, порядком выбора resolver,
  ошибками, изменениями поведения и передачей гидратора.
- [x] Создать acceptance.md с матрицей C01–C33.
- [x] Зафиксировать основание публичного решения в ADR-001.
- [x] В начале реализации сохранить baseline принятого commit в `artifacts/`.

### 2. Разделение готовности и преобразования

- [x] Ввести `ContinuationStatus`, `ContinuationState`, `ContinuationContext`,
  `ContinuationStateResolverInterface` и `FinalPathStateResolver`; добавить `stateResolver`
  в атрибут и resolver в ClientConfig.
- [x] Сначала определять состояние ответа; гидратировать только Ready.
- [x] Удалить catch-эвристику, `resolveContinuationPayload` как источник финала
  и fallback unwrap к корню.
- [x] Поддержать Sync, Auto, Async и awaitByToken* по таблицам contracts.md.
- [x] После завершения 031 передать гидратор клиента в ContinuationService.

### 3. Доставка ошибки и кешированный await

- [x] Ввести `ContinuationAwaitException` с reason, attempts, lastResult и безопасным context.
- [x] Ввести `ContinuationOutcome`; перенести гидратацию cached awaitAs в ContinuationService.
- [x] Сохранить доставку исключений failed-результата через `throw()` и
  configuration-ошибок без обёртки.

### 4. Проверки и документация

- [x] Выполнить матрицу C01–C33 на mock-транспорте с intervalMs=0 и счётчиком запросов.
- [x] Переписать существующие тесты continuation под объявленный критерий.
- [x] Выполнить `composer test`, `composer lint`, `composer analyse`, `composer check-docs`.
- [x] Обновить provider-async-await, continuation-token, response attributes, errors
  и changelog с миграцией.
- [x] Записать версию, команды и результаты в acceptance.md/artifacts/.

## Доказательства и завершение

Первая рецензия: [20 наблюдений](../../discussion/dsc-005-declarative-dto-contracts/artifacts/review-probe.json).
Дополнение с plain DTO: [followup-probe](../../discussion/dsc-005-declarative-dto-contracts/artifacts/followup-probe.php),
[результат](../../discussion/dsc-005-declarative-dto-contracts/artifacts/followup-probe.json).
Ложная готовность: [clarification-probe](../../discussion/dsc-005-declarative-dto-contracts/artifacts/clarification-probe.php),
[14 наблюдений](../../discussion/dsc-005-declarative-dto-contracts/artifacts/clarification-probe.json).
Эти исходные доказательства остаются в дискуссии; новая приёмка 030 хранится здесь.

План завершён, когда контракт реализован, матрица C01–C33 пройдена, миграция
документирована и 028 может настроить правила уже подключённого к await гидратора.
Статус меняется на `в работе` с началом этапа 2; наличие плана не закрывает дефект.
