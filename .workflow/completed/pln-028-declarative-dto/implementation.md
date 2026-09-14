# Реализация и приёмка pln-028

- Дата создания: 2026-09-15
- Дата обновления: 2026-09-15
- Статус: выполнено
- Реализация: `9e84782b1d3dc23f10ec870815d02b5392579f0a`
- Baseline перед этапом 2: `f52e836c1ecbc97f3bbfcd15a7ca284b0658b9af`

Порции A, B и C выполнены. Зависимости 031 и 030 приняты до подключения внешних
правил. [Контракт](contracts.md) и [матрица приёмки](acceptance.md) реализованы
в одном Hydrator; атрибуты, профиль и descriptors используют общие проверки,
область вызова и создание DTO. Отдельного заменяемого гидратора нет.

## Результат

- Неизменяемые HydrationRules/DtoRules/FieldRule/ValueShape/HandlerSpec/DefaultSpec
  компилируются при создании гидратора или клиента. Конфликты, receiver и ссылки
  проверяются до данных; resolved rules изолированы от reflection metadata.
- Strict проверяет native-типы и объявленные элементы до reflection; float принимает
  int только в согласованном диапазоне. Required, explicit null, форма, defaults,
  each/itemCast/discriminator выполняются в принятом порядке.
- HydrationScope передаёт тот же гидратор в casts/providers всех форм регистрации.
  Готовый DTO не создаётся повторно. Активная глубина и циклы проверяются на вызов.
- Extras вычисляются по выбранным путям, включая null/fallback, перекрытия,
  соседей each и Key-discriminator. Остатки проекций имеют стабильную рекурсивную форму.
- Сериализаторы клиента исключают receiver по классу, включая ручные plain DTO.
  Непрозрачные casts отклоняются до вызова и HTTP; DTO не изменяется.
- DTO-путь и JSON Pointer разделены. Автоматический лог получает маскированный
  logContext, результат сохраняет точный путь. Пользовательские преобразования
  отмечаются Boundary/Unavailable.
- Returns, обе формы пагинации, composite, HTTP cache hit и все пять входов await
  используют текущий набор. Готовность Ready/Pending определяется контрактом 030.

## Уточнения при реализации

1. **Query (B12).** Receiver удаляется при сборке частей запроса. URL builder,
   как и до 028, принимает только скаляры и плоские списки. DTO после удаления extra
   остаётся структурой и отклоняется до HTTP. Матрица проверяет отдельно промежуточное
   представление и этот отказ; новый способ кодирования query не добавлялся.
2. **Непрозрачный receiver (B22).** DateTimeInterface тоже исключён из допустимых
   классов receiver: ядро не раскрывает такое представление. Это закрывает тот же
   отказ по умолчанию, что JsonSerializable/Stringable/toArray, без тихой потери гарантии.
3. **Items-only (A30).** Этот прежний вход возвращал извлечённые raw items и не вызывал
   гидратор для itemsType. При подключённом наборе теперь вызывается общий путь
   hydrateCollection. Без набора результат не меняется.
4. **Происхождение.** Произвольный aggregate помечается Boundary; Ready без объявленного
   пути — Unavailable. Ошибки unwrap и формы до вызова Hydrator также получают sourcePath.

Эти уточнения отражены в профильной [публичной справке](../../../docs/guides/hydration-rules.md),
контракте и строках приёмки. Они не расширяют URL builder, не подключают старые
hydration registry и не меняют правила без набора.

## Связь строк приёмки с проверками

Таблица указывает основные проверки; общий прогон включает сохранённые регрессии
029/030/031. Проверки builders/компиляции исполняются до данных; правила преобразования
проверены standalone, а сквозные сценарии и типы — также через MockTransport/Returns.
PHP-объекты, циклы, enum literals и DX проверяются через подходящий прямой вход.

| Строки | Проверки |
| --- | --- |
| A01, B01–B02, B10, C01 | [ExternalHydrationRulesTest](../../../tests/Unit/Serialization/ExternalHydrationRulesTest.php): граф без атрибутов, standalone/Returns, unwrap, extras и неизменность ввода |
| A02–A04, A06, A08–A09, A13–A15, A18, A26, A36 | [ExternalHydrationScopeTest](../../../tests/Unit/Serialization/ExternalHydrationScopeTest.php): общий metadata cache, два набора, scoped регистрации и помощники, constructor 1, provider isolation |
| A05, A07–A08, A10–A12, A14, A22–A23, A25, A29, B08–B09, B22 | [ExternalHydrationConfigurationTest](../../../tests/Unit/Serialization/ExternalHydrationConfigurationTest.php): ошибки компиляции, policy, атрибуты, null/default, формы и receiver |
| A16–A17, A19–A21, A24, A37 | [ExternalHydrationScalarTest](../../../tests/Unit/Serialization/ExternalHydrationScalarTest.php): strict standalone/Returns, float/union границы, списки, nullable, unit/backed defaults |
| A28, B02–B08, B10–B11 | [ExternalHydrationExtrasTest](../../../tests/Unit/Serialization/ExternalHydrationExtrasTest.php): перекрытия и перестановка mapping, each, рекурсивные проекции, numeric sourceKey, discriminator |
| A27, A30–A33, C01, C08 | [ExternalHydrationEntriesTest](../../../tests/Unit/Serialization/ExternalHydrationEntriesTest.php): атрибутный Nested, sync/promise, items-only/container, composite, общий HTTP cache, bypass, Sync/Auto/Async/token/token-as, cached awaitAs и previous |
| A34, C02–C09 | [ExternalHydrationOriginTest](../../../tests/Unit/Serialization/ExternalHydrationOriginTest.php): fallback, Expected, JSON Pointer, Skip/normalizeKeys, секретные ключи и debug=false, hook/computed/toArray/provider/JsonCast, глубина 512/513 и цикл |
| B12–B23 | [ExternalHydrationWireTest](../../../tests/Unit/Serialization/ExternalHydrationWireTest.php) и ConfigurationTest: plain/DtoInterface, BodyRoot/Body/multipart/query, DX, manual DTO, исходящие атрибуты, opaque boundary, requestDebug/cache/log |
| A35, A01, A14, B12, C01 | [Standalone smoke](../../../tests/Support/standalone-hydration-rules-smoke.php): выполняет PHP-блоки публичного гайда; включён в оба dist-прогона без dev-зависимостей |

## Проверки

| Команда / область | Результат |
| --- | --- |
| Новые регрессии 028 | [237 passed / 683 assertions](artifacts/implementation-new-tests.log) |
| Serialization/DataTransfer/Result/Pipeline | [941 passed / 3162 assertions](artifacts/implementation-focused-tests.log), baseline 704/2479 |
| `composer test` | [2027 passed / 7308 assertions; 17 Redis skipped](artifacts/implementation-tests.log) |
| `composer lint` | [0 errors / 168 предупреждений длины строк](artifacts/implementation-lint.log) |
| `composer analyse` | [Без ошибок](artifacts/implementation-analyse.log) |
| PHP syntax | [Все изменённые PHP-файлы](artifacts/implementation-syntax.json) |
| `composer dump-autoload --optimize --strict-psr` | [Успешно](artifacts/implementation-autoload.log) |
| `composer check-docs` | [90 документов](artifacts/implementation-public-docs.log) |
| Публичные примеры | [7 ожидаемых наблюдений](artifacts/implementation-doc-examples.json); ожидания заданы вручную в standalone smoke |
| Git/Composer dist | [618 одинаковых файлов; 15 standalone smoke в каждом архиве](artifacts/implementation-package.json), [лог](artifacts/implementation-package.log) |
| Ссылки и якоря workflow и затронутых публичных документов | [Результат](artifacts/implementation-workflow-docs.json), [проверяющий скрипт](artifacts/check-implementation-docs.py) |

[Implementation state](artifacts/implementation-state.json) связывает commit, команды,
SHA-256 исходников и артефактов. Package report хранит проверенный Git index;
после этого менялись только исключённые из dist тесты. Состав опубликованных файлов
сверен с commit реализации. Baseline сохранён до изменений; старые readiness-probe,
аудиты и их контрольные суммы не переписывались. Промежуточные implementation-логи
сохранены отдельно от окончательной приёмки.

031, 030 и 028 завершены. [027](../../current/pln-027-documentation-restructure/pln-027-readme.md)
остаётся отдельной отложенной реорганизацией документации; обновлённые руководства
028 не требуют её выполнения.
