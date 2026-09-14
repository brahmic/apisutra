# Покрытие критериев фидбека AS-1–AS-4

**2026-09-15:** устранение ограничений принято в [028](../../completed/pln-028-declarative-dto/implementation.md).
Таблица ниже относится к исходной проверенной версии.

Источник — [требования](../../issue/iss-002-apisutra-reuse/upstream-requirements.md), выводы — [аудит](aud-006-readme.md).
Идентификаторы проверок относятся к [probe-results.json](artifacts/probe-results.json).
«Частично» означает, что описанный способ имеет ограничения, существенные для полного
контракта чистых DTO. Отсутствие API устанавливается чтением точек разрешения правил,
а не только неудачей одного примера.

Повторная сверка [оригинала A01–A08](recheck.md) добавила сценарии `R01–R14`
из [extensions-results.json](artifacts/extensions-results.json) и уточнила AS-4:
provider с `when: Present` совместим с Nested.

[Проверка рецензии](peer-review.md) добавила `C01–C35` из
[peer-review-results.json](artifacts/peer-review-results.json). Способы с атрибутами
оцениваются отдельно от заявленного чистого графа. Present-provider воспроизведён,
но пока не является документированным рецептом, закреплённым основными тестами.

| Критерий | Проверка текущего состояния | Оценка |
| --- | --- | --- |
| AS-1: int, bool, string из таблицы | `AS1-default-1..18`, `AS1-profile-1..18`: профильные casts отвергают неявные преобразования, native-значения проходят. | Решается для DTO с профильной привязкой. |
| AS-1: min/max и переполнение int | `AS1-default-3/4`, `AS1-profile-3/4`, `AS1-overflow`, `AS1-profile-overflow`. | Отказ сохранён; у пользовательского строгого cast reason другой. |
| AS-1: вложенные объекты и элементы | `AS1-child`, `AS1-items` против `AS1-unbound-child`. | Профиль действует по иерархии типов, не автоматически по всему независимому графу. |
| AS-1: scalar-элементы списка | `C09–C14`, `C34–C35`: PHPDoc list<int> не проверяется; itemCast проверяет каждый элемент, аргументов не принимает; параметризованный property Cast работает. | F9: нужны явно объявленные типы элементов во внешних правилах. Исходные AS1-items проверяли только DTO-элементы. |
| AS-1: явный cast и проверка его результата | `AS1-explicit-cast`, `AS1-after-cast`, `AS1-after-cast-array`. | Приоритет и проверка совместимости есть; строгого запрета scalar conversion после cast нет. |
| AS-1: изоляция клиента/standalone | `AS1-isolation`, `AS1-registry`, `AS1-client-casts`; resolver читает атрибут класса. | Class-bound профиль не меняет default, но разные внешние настройки одного DTO-класса не поддержаны. |
| AS-2.1: receiver неизвестных/немоделируемых данных | `AS2-extras`; Hydrator перебирает только свойства. | Не реализовано. |
| AS-2.2: falsy/вложенные данные и receiver каждого DTO | `AS2-extras`, `AS2-raw-response`; каждый вызов Hydrator не собирает остаток. | Raw сохранён, автоматический receiver отсутствует. |
| AS-2.3: mapping/dot-path/fallback и неиспользованные aliases | `AS3-fallback`; resolveValueWithFallbacks не возвращает путь, Hydrator не хранит consumed paths. | Выбор значения есть; вычисления остатка с leaf-гранулярностью нет. |
| AS-2.4: коллизия имени receiver | `AS2-collision`: extras источника читается обычным полем, другие ключи игнорируются. | Специального правила коллизии нет. |
| AS-2.5: opt-in и отсутствие неявного outbound merge | Нет механизма receiver; DTO/wire сериализация независима от предполагаемого нового поведения. | Требуется определить при добавлении; текущую сериализацию не менять автоматически. |
| AS-3.1: единая внешняя декларация mapping/object/list/discriminator | `AS3-plain-list`, `AS3-discriminator`, `AS3-plain-nested`; чтение metadata и profile resolver. | Части доступны через атрибуты, внешняя декларация отсутствует. |
| AS-3.1: одиночный объект через Nested | `C01–C08`: array из JSON трактуется списком; PHP-объект принимается; property Cast обходит проблему. | F8: документированный сценарий сломан, исправление отдельно в pln-029. Нельзя считать одиночный Nested рабочей основой pln-028 до исправления. |
| AS-3.2: standalone и Returns с теми же правилами | `AS3-plain-object`, `AS1-pipeline-*`, `C31–C32`; public hydrate не требует контекста. | Входы есть; внешний набор отсутствует. MIME-handler с ненулевым результатом заменяет unwrap/гидратацию, а не подключает правила к Returns. |
| AS-3.3: constructor once, required/native checks, без I/O | `AS3-constructor*`, `AS4-required-nullable`; существующие HydrationCompatibility/ErrorContract tests. | Выполняется в текущем поддерживаемом пути; native scalar conversions соответствуют defaults. |
| AS-3.4: независимость наборов для одного DTO-класса | Class-bound final resolver, private client hydrator, `AS1-client-casts`. | Внешних наборов нет. Глобальная регистрация не является решением изоляции. |
| AS-3.5: совместимость атрибутов и приоритет | Атрибутный путь работает; нового источника правил нет. | Приоритет нужно задать при добавлении внешних правил. |
| AS-3.6: reason и путь исходных данных с unwrap/index | `AS3-source-path`, `AS3-unwrap-path`. | Reason, DTO path, unwrap и порядковый индекс есть; sourcePath отсутствует. |
| AS-4: optional missing/default и explicit-null rejection | `AS4-missing-default`, `AS4-null-cast`, `AS4-provider-*`, `C15–C19`, `C33`. | Provider позволяет отказать; при Keep видит исходное состояние. Оно может меняться при non-Keep/EmptyStringAsNull, а Cast пропускает нормализацию. F5 — неполный путь вложенного поля. |
| AS-4: required nullable parameter vs nullable property | `C20–C22`: параметр без default требует ключ; nullable-свойство вне конструктора получает null. | Исходное утверждение об обязательности ограничено параметрами конструктора. Старое поведение свойств сохраняется без новой политики. |
| AS-4: значение/строка независимо от missing/default | `AS4-provider-value`, `AS1-*`; DefaultValue.when выбирается по состоянию. | Для текущего пути работает; строгий режим ограничен AS-1. |
| AS-4: list/empty/map/sparse | `AS4-nested-*`, `AS4-cast-*`, `R01–R08`, `R14`. | Custom cast решает простой список, а DefaultValue provider с Present проверяет форму перед Nested/discriminator. У provider нет автоматического пути поля. |
| AS-4: каждый объявленный уровень и составление с Nested | `AS4-cast-nested-combination` против `R01–R14`: property Cast пропускается, provider работает; itemCast + hydrateCollection проверяют внутренний ряд. | Двумерный список решается без DTO ряда и собственного mapper. Чистый граф без атрибутов и путь ошибки provider требуют доработки. |
| AS-4: совместный null/shape provider | `C23–C28`: DefaultValue не повторяемый; один provider с Null/Present работает, дефект пути внешней формы сохраняется. | Комбинация подтверждена. Закрепление Present в tests/ и руководстве — pln-029; внешние правила — pln-028. |
| AS-4: обязательная коллекция не становится пустой без декларации | `AS4-list-missing`, `AS4-typed-missing`, `AS4-typed-guard`. | Array уже требует присутствия; typed collection имеет legacy autodefault и обход через provider. |
| Общая приёмка: одинаковые defaults у существующих пользователей | HydrationCompatibilityTest: разрешённые conversions, defaults/fallback/null и конструкция DTO. | Проверенный совместимый baseline; менять его глобально не требуется. |
| Общая приёмка: без Laravel/сети и ручного PipelineContext | Probe блокирует автозагрузку Illuminate; standalone использует hydrate без контекста, pipeline — MockTransport и NullContainerProvider. | Для проверенных primitives выполняется. |
| Общая приёмка: ошибки данных/конфигурации, HTTP и безопасный context | `AS1-pipeline-*`, `AS3-unwrap-path`, `AS4-provider-pipeline`, `C04–C05`, `C14–C16`; 135 tests. | Помимо sourcePath, F5 теряет сегмент поля, F8 даёт TypeError standalone и HydrationException без reason/path в HTTP; неверная конструкция itemCast даёт ArgumentCountError. Нельзя распространять положительные проверки на эти сценарии. |
| F7: все источники registry-casts | `AS1-registry`, `AS1-client-casts`, `C29–C30`, контроль профильного cast. | Constructor/client/global/extension registry не участвуют в гидратации; документация и тест источника исправляются отдельно от внешних правил. |
| Общая приёмка: known invalid не превращается в raw | `AS3-known-invalid` и `AS3-discriminator`; NestedDiscriminatorModeTest. | Выполняется; переиспользовать при расширении. |
| Общая приёмка: версия, commit и команда | artifacts/baseline.json, artifacts/README.md, сохранённые результаты. | Зафиксированы. |
