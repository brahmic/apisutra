# Реализация 032

База реализации: `24e3d581952e77588a2c690678d8d5d04748addd`, PHP 8.4.15.
Итоговый commit определяется по `git log --all --grep='feat(hydration): реализовать план 032'`.

## Изменение

`FieldRule::constructorValue()` компилируется вместе с существующим набором.
Compiler проверяет native-типы, конструктор, hooks/default и форму без выполнения
пользовательского кода. Hydrator хранит отложенные проверки локально для одного узла,
сохраняя HydratedProperty и SourceLocation. После единственного конструктора
проверяются отмеченные поля; затем продолжаются обычные присваивания.

`ConstructorValues` проверяет весь допустимый домен перед сравнением. Обход линейный
по количеству элементов, стек ограничен 512 array-контейнерами. Сортировки,
сериализации, обхода свойств произвольных объектов и записей в отмеченное поле нет.
`NativePropertyValue` завершает Legacy-типизацию через ограниченный набор типизированных
scalar-функций и ReflectionFunction. Это использует правила движка после кастера
пакета, без пробного DTO, eval или повторного преобразования. Exact native значения
сохраняются. Strict обрабатывается прежним ScalarValues.

## Матрица приёмки

Все тесты ниже находятся в `tests/Unit/Serialization/`; именованные фикстуры —
`tests/Stubs/ConstructorOwned/`, без внешнего SDK или реальной сети.

| Строки | Доказательство |
| --- | --- |
| D01–D05 | ConstructorOwnedFieldsTest: readonly/mutable, before-constructor ошибки, allowMissing/required |
| D06–D09 | FieldsTest и ConstructorOwnedDeclarationsTest: value/provider defaults, Null/nullable, empty string и cast |
| D10 | FieldsTest: ручные ожидания и дифференциальное сравнение обычных полей, noTransform/cast и два порядка union; DeclarationsTest: Strict 2^53 |
| D11–D12 | DeclarationsTest: backed/unit enum, true/false, NAN, незаполненное поле и исключение конструктора |
| D13–D14 | DeclarationsTest: 12 неподходящих полей, отсутствие конструктора, вложенные DTO/variants; прежние ExternalHydrationConfigurationTest и проверки конфликтов набора; EntriesTest: наследование и To |
| D15–D17 | FieldsTest и ConstructorOwnedEntriesTest: fallback/Expected/Boundary, extras, nested/each/list/variants |
| D18–D19 | EntriesTest: Returns sync/async, dataOrFail/throwOnErrors, HTTP 200, cache hit с новым набором, pagination/composite, Ready и повторный awaitAs |
| D20–D21 | FieldsTest/ArraysTest: opt-in, cache-off/cold/warm, чередование исходов и два набора; прежние HydrationCompatibilityTest и metadata-isolation тесты 031 в общем прогоне |
| D22–D23 | EntriesTest/ArraysTest: mapping/unwrap, граница cast и безопасная диагностика, ручной исходящий DTO с To и receiver |
| D24–D27 | ConstructorOwnedArraysTest: списки, словари, recursive/null/enum/NAN, PHP-ключи, пустой JSON и порядок numeric keys |
| D28–D29 | ArraysTest/DeclarationsTest: scalar-строки/float-листья не приводятся сравнением, array union, list/each/normalizeKeys, enum cases |
| D30–D33 | ArraysTest: объекты/ресурсы на обеих сторонах, allowMissing, 511/512/513, цикл и повтор ветви; DeclarationsTest: provider/default для array |
| D34–D35 | ArraysTest/EntriesTest: источник всего свойства после mapping/cast, секретные ключи, широкий словарь, кеш и неизменность входа |
| D36 | EntriesTest: словарь через Returns и повторное Ready awaitAs, доставка mismatch без polling |

Дополнительные пути shape/itemCast и атрибутные конфликты сохраняют существующие
тесты внешних правил; новая проверка вызывается после их результата, специальных
веток в клиентах, пагинации и continuation не добавлено.

## Проверки

[Команды и исходы](artifacts/implementation/commands.json),
[адресный прогон](artifacts/implementation/target.stdout): **97 passed / 456 assertions**.
[Общий повторный прогон](artifacts/implementation/tests-recheck.stdout):
**2124 passed / 7764 assertions, 17 skipped** (условия среды, не новые skip).
Первый общий прогон дал сбой существующего TransportTimeoutIntegrationTest:
200 вместо timeout у локального worker. [Первый лог](artifacts/implementation/tests.stdout)
сохранён; [отдельная перепроверка](artifacts/implementation/timeout-recheck.stdout)
и общий повтор прошли. Код этого стенда не менялся.

Целевой PHPStan, PSR-12 без baseline-предупреждений длины строк, analyse-docs,
check-docs (**143 документа / 1414 ссылок**) и новый standalone-пример прошли.
[Проверка двух архивов](artifacts/implementation/package.json) включает все standalone
smoke без dev-зависимостей. Whitespace проверен перед commit.

[Baseline](artifacts/implementation-baseline/report.json) хранит среду отдельно
от наблюдений issue. После реализации 19 наблюдений оригинального probe совпали,
включая U15–U19 без opt-in; [сравнение](artifacts/implementation/upstream-comparison.json).
Исторические SHA/path исходников относятся к записанным commit и не обещают совпадения
с будущим деревом после 033.

## Документация

Добавлены [контракт пользователя](../../../docs/reference/dto/constructor-values.md)
и исполняемый пример `docs/example/constructor-values/`, подключённый к проверке dist.
Обновлены справочники fields/models/defaults/diagnostics, plain DTO, DTO showcase,
реестр docs-api и CHANEGLOG. Нового глобального режима или миграции для 032 нет.
