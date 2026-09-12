# Контракты полей DTO, строгий JsonCast и безопасные ошибки гидратации

- Дата создания: 2026-09-12
- Дата обновления: 2026-09-12
- Статус: завершён

## Цель и основание

Следующая ограниченная поставка [AS-07](../../issue/001/technical-specification.md#10-as-07--json-и-типы-данных):
не выдавать повреждённый вложенный JSON за успешный null, различать отсутствие
обязательных данных и ошибку конфигурации, показывать место ошибки без раскрытия
значения. Также устраняется воспроизведённая утечка в диагностике (AS-10).

Предыдущая поставка [pln-012](../../completed/pln-012-strict-unwrap-big-integers/pln-012-readme.md)
добавила strict Returns::unwrap, точные большие целые и структурированную
HydrationException. Эта инфраструктура переиспользуется, обязательных настроек
ClientConfig, новых required/nullable-атрибутов или глобального strict-режима не нужно.

При подготовке проверена **рабочая копия с реализацией pln-012**, ещё не включённой в коммит.
HEAD — `341a72e`; сам по себе этот коммит не воспроизводит проверенную версию.
Для ключевых исходников сохранены [SHA-256 рабочей копии](artifacts/source-sha256.txt).
Реализация pln-013 выполнена по поручению пользователя 2026-09-12. D1–D3 согласованы
пользователем 2026-09-12; D3 уточнён после обсуждения пользы исходных значений
для расследования: автоматические логи безопасны, исходный HTTP-ответ остаётся
доступным вызывающему коду. Исходники, тесты и публичные документы в рамках
подготовки не менялись.

## Проверка и воспроизводимость

Методика: текущие src, документация и тесты; вызовы DTO::from() и pipeline через
MockTransport; локальный logger с уровнем ERROR; положительные контрольные сценарии.
PHP 8.5.4, 64-bit, без реальной сети и внешних API. Архив history не использовался.

[Probe](artifacts/probe.php) и три вспомогательных типа расположены в artifacts.
Запуск из корня репозитория:

```bash
php .workflow/completed/pln-013-dto-hydration-contracts/artifacts/probe.php
```

[Исходный результат](artifacts/baseline.jsonl) сохранён отдельно. В нём только
искусственные данные и признаки утечки, реальные секреты не используются.
Script показывает наблюдения, а не приёмку будущего поведения; после исправления
результаты должны измениться. Вспомогательные типы подключаются явно, autoload не меняется.

| ID / приоритет | Воспроизведённое поведение | Вывод |
| --- | --- | --- |
| F1 / P1 | Missing/null/массив вместо обязательного int: pipeline даёт hydration_error без path; DTO::from() бросает ArgumentCountError/TypeError. | Нужна единая типизированная ошибка с полем, состоянием и типом. Успех здесь уже не выдаётся. |
| F2 / P1 | Missing обязательного inherited-свойства вне constructor chain даёт configuration_error. | Отсутствие данных ответа неверно классифицировано как конфигурация SDK. |
| F3 / P1 | Ошибка в items[1].id и scalar вместо items[0] теряют путь, прямой вызов даёт TypeError. | Поле и индекс нужно сохранять при рекурсивной гидратации. |
| F4 / P1 | JsonCast для повреждённой строки и пустой строки возвращает null; DTO и HTTP 200 считаются успешными. Валидный JSON null неотличим. | Строковый JSON надо разбирать строго независимо от nullable/mixed поля. |
| F5 / P1 | Невалидная дата с политикой Throw даёт configuration_error; искусственный секрет попадает в message и ERROR log. | Ошибка данных даты должна быть hydration_error с безопасным сообщением. |
| F6 / P1 | Неизвестный Nested discriminator при явно заданном Error даёт configuration_error; исходный ключ попадает в message и ERROR log. | Сохранить выбранный отказ, изменить классификацию и убрать значение из диагностики. |
| C1 / контракт | Nullable-параметр конструктора без default: missing ошибочен, explicit null успешен. Nullable inherited-свойство без constructor parameter: missing успешен. | Не унифицировать эти формы новым поведением; сохранить constructor-first. |
| C2 / контракт | Constructor default сохраняется при missing; null его не заменяет; fallback не применяется поверх найденного null. | Сохранить действующий порядок разрешения значения. |
| C3 / контракт | DateTimeInvalidBehavior::Null сохраняет успех с null; JsonCast сохраняет false и точный bigint. | Явную политику дат и корректные JSON-значения сохранить. |

Затронутый базовый прогон:

```bash
vendor/bin/pest tests/Unit/Serialization/HydratorTest.php \
  tests/Unit/Serialization/DateTimePolicyTest.php \
  tests/Unit/Serialization/NestedDiscriminatorModeTest.php \
  tests/Unit/Serialization/StrictUnwrapBigIntegerTest.php \
  tests/Unit/Serialization/BigIntegerIntegrationTest.php \
  tests/Unit/Pipeline/JsonErrorContractsTest.php
```

Результат: **165 passed, 535 assertions**, 0.18 s. Часть этих тестов намеренно
закрепляет прежние классы ошибок; зелёный прогон не опровергает F1–F6.
Полный suite подготовки не запускался: изменений поведения ещё нет.

## Действующие механизмы

[Hydrator](../../../src/Serialization/Hydrator.php) уже использует ValueState,
From/fallback, DefaultValue, constructor defaults и автодефолт non-nullable typed
collections. Публичный [контракт DTO](../../../docs/guides/dto.md) закрепляет
constructor-first и nullable fallback вне constructor chain. Их не нужно заменять.

[BuiltinHydrationCaster](../../../src/Serialization/BuiltinHydrationCaster.php)
выбирает атрибутный/профильный cast, затем встроенное преобразование.
[PropertyTypeInspector](../../../src/Serialization/PropertyTypeInspector.php) и
[HydrationTypeSelector](../../../src/Serialization/HydrationTypeSelector.php)
дают существующие metadata и порядок union-веток. После этого reflection может
выполнять дополнительные допустимые PHP scalar conversions. Нельзя заменить весь
поток проверкой точного runtime-типа и незаметно запретить успешные преобразования.

[HydrationException](../../../src/Exceptions/Serialization/HydrationException.php)
уже содержит reason/path/expected/actual и prependPath. Добавление пути сейчас
происходит только при исключении этого класса; TypeError и ошибки кастов обходят
эти границы. [ResponseHydrator](../../../src/Pipeline/Hydration/ResponseHydrator.php)
оборачивает произвольную ошибку только на внешней границе DTO, где поле уже потеряно.

[JsonCast](../../../src/Casts/JsonCast.php) использует JSON_BIGINT_AS_STRING, но не
JSON_THROW_ON_ERROR. [DateTimeCast](../../../src/Casts/DateTimeCast.php) добавляет
исходную строку в ConfigurationException. Неизвестный Nested discriminator в Error
обрабатывается аналогично в Hydrator.

## Согласованные решения

### D1. Обязательность определяется существующим объявлением DTO

**Принято: сохранить успешные missing/null/default сценарии, унифицировать ошибки.**
После From/fallback, нормализации EmptyString, применимого DefaultValue и
действующего автодефолта коллекций:

| Объявление / вход | Целевое поведение |
| --- | --- |
| Constructor parameter с default, поле missing | Использовать constructor default. |
| Constructor parameter без default, поле missing, включая ?T | hydration_error: required_field_missing. Nullable разрешает значение null, но не отменяет обязательность аргумента. |
| Public nullable property вне constructor chain, без default, поле missing | Существующий fallback null. |
| Обязательное public property вне constructor chain без default, поле missing | hydration_error: required_field_missing вместо configuration_error. |
| Explicit null и nullable/mixed | Принять null, если существующий DefaultValue для Null не задаёт замену. |
| Explicit null после существующих правил, итоговый тип не допускает null | hydration_error: null_not_allowed. |
| Неподходящая форма данных после допустимых casts | hydration_error: invalid_field_type / unexpected_response_shape с путём. |

Не добавлять автоматический null для обязательного nullable constructor parameter.
Не заставлять пользователя указывать required-флаги поверх PHP-типа и default.
Не расширять поддержку произвольных mutable/private/property-hook DTO в этой поставке.
Существующие supported defaults не должны затеряться при перестройке проверок.

Продуктовый пример: `?string $comment = null` допускает отсутствие комментария;
`?string $comment` в конструкторе требует, чтобы поле пришло, хотя его значение может
быть null. Ошибка должна объяснить разницу вместо общего TypeError.

### D2. JsonCast разбирает строки строго по умолчанию

**Принято: JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING без новой настройки.**
Malformed JSON, пустая/пробельная строка, неверный UTF-8 или превышение глубины 512
дают HydrationException с reason `invalid_json`, original JsonException в previous
и путём поля при вызове из Hydrator. В pipeline это hydration_error с HTTP-контекстом;
вызов JsonCast::hydrate() напрямую также бросает HydrationException.

Валидные `null`, false, 0, строка, объект/массив и bigint сохраняются; PHP null
остаётся null. Нестроковое значение JsonCast по-прежнему передаёт дальше без разбора.
Nullable/mixed свойства не скрывают malformed JSON. Итоговый тип поля проверяется
отдельно: валидный JSON scalar может не соответствовать array-полю.

Продуктовый пример: поле `settings` содержит строку `{"currency":` — SDK сообщает
о повреждённых настройках, а не возвращает успешные `settings=null`.

Если провайдер использует пустую строку вместо отсутствия JSON, он явно нормализует
её через BeforeHydrate или собственный cast. Нового permissive-флага не вводить.
JsonCast::serialize и ProviderResponse::json() не меняются: permissive HTTP accessor
не является явным контрактом JSON-поля DTO. Классификация верхнего JSON-ответа
остаётся response_decoding_error, вложенного JSON поля — hydration_error.

### D3. Безопасные логи и доступ к исходным данным для расследования

**Принято: расширить существующую HydrationException, сохранив отдельные ошибки конфигурации и исходный HTTP-ответ.**
В целевом объёме missing/null/неверная форма данных, невалидный вложенный JSON,
непринятая дата и неизвестный Nested variant в Error дают hydration_error.
Для дат сохраняется выбранная политика Throw/Null, для Nested — Error/Skip/KeepRaw.
Успешные режимы Null/Skip/KeepRaw не ужесточаются.

Путь строится из объявленного unwrap, имён DTO properties и порядковых индексов:
`data.items[2].price`. Исходные From/fallback/динамические ключи не раскрываются
через значения; path означает путь DTO, дополненный unwrap, а не обязательно
буквальный путь внутри ответа провайдера. Для корневой проверки использовать `$`.
Expected описывает требуемый тип/форму; actual — runtime-тип или missing/null,
а не содержимое или перечень ключей объекта.

Предлагаемые дополнительные reason: `required_field_missing`, `null_not_allowed`,
`invalid_field_type`, `invalid_json`, `invalid_datetime`, `unknown_nested_variant`.
Существующие причины pln-012 остаются. Message/error context/обычный logger содержат
безопасную диагностику. Не конкатенировать сообщение вложенного исключения или
исходное значение. Previous сохраняет техническую причину там, где она есть;
ручное чтение raw response/previous не объявляется очищенным экспортом.

Уточнение по результатам обсуждения с пользователем:

- Автоматический ERROR log содержит reason/path/expected/actual, HTTP status,
  класс запроса и traceId. Для даты expected включает объявленный формат; для
  неизвестного Nested variant диагностика может перечислить допустимые варианты
  из объявления SDK, без неизвестного значения ответа.
- `ExecutionResult::response` и его raw body сохраняются без маскирования или
  пересборки из-за ошибки гидратации. Получение исходного ответа для расследования
  не требует включения debug или новой настройки ClientConfig.
- Провайдер может явно сохранить нужные исходные данные при обработке результата
  по собственным правилам доступа и маскирования. SDK не добавляет обязательную
  систему хранения диагностических ответов или глобальный режим неочищенных логов.
- В документации показать два действия: найти нарушенный контракт по безопасной
  диагностике и при необходимости прочитать исходный ответ из результата.

Неверный класс DTO/каста, некорректные атрибуты/профили/таймзоны, конфликт инициализации
readonly property остаются ошибками конфигурации. Не переименовывать все
ConfigurationException в HydrationException и не перехватывать любой сбой
пользовательского конструктора/computed как ошибку конкретного поля.
Пользовательские casts сохраняют приоритет; безопасная структурированная ошибка
данных от них может дополняться путём. Произвольные пользовательские сообщения
не объявляются автоматически безопасными.

Продуктовый пример: ошибка `items[2].price: ожидался float, получен array` позволяет
найти нарушение контракта без публикации персональных данных из элемента заказа.

## Реализация по шагам

1. Добавить регрессионные tests/stubs по F1–F6. Артефакты подготовки оставить
   историческими. Закрепить положительную матрицу defaults/null/fallback/коллекций
   и успешных scalar conversions, чтобы проверка типов не стала скрытым strict-режимом.
2. В Hydrator разделить разрешение значения, проверку входной формы/типа и
   инициализацию. Переиспользовать metadata/ValueState; недостающий аргумент и
   невозможный тип выявлять до вызова пользовательского конструктора/reflection.
   Учитывать типы constructor parameters и public properties, включая union и
   наследование. Сам пользовательский конструктор не вызывать пробно.
3. Локализовать рекурсивные ошибки: обычный nested DTO, Nested collections/each/itemCast,
   discriminator, hydrateCollection и пагинационные items. Проверять форму элемента
   до передачи в публичный hydrate(array|object), сохраняя его сигнатуру.
   Не строить поле из парсинга текста TypeError.
4. Сделать JsonCast::hydrate строгим по D2, сохранив bigint и previous.
5. В DateTimeCast отличать невалидные данные от невалидной настройки. В ветке
   NestedUnknownVariant::Error возвращать безопасную ошибку данных без discriminator.
   Проверять декларации карт/классов как конфигурацию. При нормализации EmptyString
   в null не обходить правила nullable; ошибка итогового non-nullable поля относится
   к данным после преобразования.
6. Проверить доставку error context через прямой DTO::from()/Hydrator/JsonCast,
   raw/resolved/dataOrFail/throwOnErrors, sync/async. Стандартный pipeline сохраняет
   HTTP 200 и body; локальная ошибка гидратации не запускает новый HTTP retry.
   Передать структурированную диагностику в автоматический logger, проверить
   неизменность raw body и его доступность при выключенном debug.
   Проверить поведение при повторном чтении из кеша без пересмотра cache policy.
7. Обновить публичные документы и migration notes, затем targeted tests,
   composer test, strict-PSR autoload при новых типах и standalone JSON smoke без Laravel.
   Записать результаты, обновить changelog без внутренних ссылок, перенести комплект
   в completed только после приёмки.

## Матрица приёмки

| Область | Проверки |
| --- | --- |
| Missing/default/null | Required constructor, nullable без default/с default, inherited required/nullable, From fallback, DefaultValue по Missing/Null, explicit null, EmptyString policies, defaults коллекций. |
| Типы | array вместо scalar, scalar вместо DTO/элемента коллекции, nullable nested DTO, union/runtime selection, безопасные casts и действующие scalar conversions; int overflow pln-012 сохраняется. |
| JsonCast | Malformed, пустая строка, whitespace, invalid UTF-8, depth overflow, PHP null, JSON null/false/0/string/[]/{}, bigint; прямой и вложенный вызов, nullable и array поля. |
| Пути | Вложенный DTO, items[1].id, From alias, unwrap, Nested each/itemCast/discriminator, pagination collection. Динамический ключ/значение не попадают в путь. |
| Ошибки/утечки | Дата Throw без исходной строки в message/log, с объявленным форматом; дата Null без регрессии; Nested Error без исходного discriminator; Skip/KeepRaw сохранены; неверная конфигурация остаётся configuration_error. Автоматический лог содержит структурированные сведения и traceId. |
| Подробная диагностика | При debug=false исходный HTTP-ответ и body доступны в результате и не изменены; искусственный секрет присутствует в raw body, отсутствует в автоматическом message/error context/log. |
| Доставка | Прямой вызов, raw/resolved/dataOrFail/throwOnErrors, sync/promise, HTTP сохранён, число HTTP-вызовов, повторное чтение кеша; пользовательские hook/constructor/cast не становятся пробными вызовами. |
| Регрессии | Конструктор вызывается один раз; readonly/inherited initialization, DTO profiles, serialization/wire omission, strict unwrap, big integers, существующие successful-response контракты не меняются. |

Полный suite обязателен после реализации. В частности, текущие ожидания TypeError
в HydratorTest, ConfigurationException для inherited missing/дат/Nested Error и
permissive JsonCast в StrictUnwrapBigIntegerTest должны быть осмысленно обновлены.

## Совместимость, документация и границы

Изменение **не полностью обратно совместимо**:

- Повреждённая JSON-строка/пустая строка в JsonCast больше не даст успешный null.
- Прямой DTO::from() для выбранных ошибок данных даст HydrationException вместо
  ArgumentCountError/TypeError/ConfigurationException. Нужно обновить catch и тесты.
- Ошибки данных inherited/дат/Nested Error сменят SDK code с configuration_error
  на hydration_error; сообщения перестанут включать исходное значение.

Успешные defaults и nullable сценарии сохраняются по D1. Новых обязательных настроек
и зависимостей нет. Интеграции, читающие enum/error code/message, должны учитывать
миграцию. Структура самого результата не меняется.

Обновить [DTO](../../../docs/guides/dto.md), [casts](../../../docs/guides/casts.md),
[ошибки](../../../docs/guides/errors.md), [настройки сериализации](../../../docs/guides/client-config/serialization.md)
и [тестирование](../../../docs/guides/testing.md); полную спецификацию правил полей
сосредоточить в DTO, в остальных местах дать короткую ссылку. Changelog описывает
наблюдаемое поведение без ссылок на workflow.

Не входят: прямой nullable root DTO, различение JSON object/list на уровне decoder,
новый протокол отсутствующих полей, общая перепись DTO/wire serialization, изменения
EnumCast unknown-to-null, permissive ProviderResponse::json, continuation/polling,
правила PaginationConfig.itemsPath, mutable/private/property-hook DTO, произвольные
пользовательские бизнес-исключения и новые глобальные политики. Весь AS-07 и AS-10
не объявляются закрытыми без отдельной сверки оставшихся требований.

## Результат реализации

Подготовка завершена, D1–D3 согласованы с уточнением диагностики 2026-09-12.
Существующие defaults/nullable сохраняются, вложенный JSON становится строгим,
автоматические логи безопасны, исходный HTTP-ответ доступен для подробного разбора.
Реализация начата 2026-09-12 после коммита pln-012: `df1cb4d`.
Проверены синтаксис четырёх PHP-артефактов, 16 записей baseline JSONL,
локальные ссылки и `git diff --check`.


Реализация завершена 2026-09-12. Добавлена проверка итоговых значений перед
reflection/constructor, с сохранением разрешённых scalar conversions и отдельных
типов параметра/свойства. JsonCast стал строгим; дата Throw и Nested Error получают
структурированную ошибку. Ошибка типа входа EnumCast локализуется; unknown-to-null
для допустимого backed value сохраняется. Проверка деклараций не заменяет
пользовательские business exceptions ошибками поля.

Автоматический ERROR log дополнен reason/path/expected/actual, HTTP status и traceId;
сырой HTTP-ответ остаётся неизменным. Для кеша смоделирован сохранённый ответ,
не соответствующий текущему DTO: sync/promise и throwOnErrors дают hydration_error
без нового HTTP (один вызов только для прогрева). Политика записи кеша не менялась.

Публичные DTO/casts/errors/client-config/testing и CHANEGLOG обновлены, включая
несовместимые изменения и получение raw body без debug. Обязательных настроек нет.
Исходные baseline и SHA-256 не изменены; [повторный probe](artifacts/after.jsonl)
содержит 16 записей, значения не раскрываются в message/log, успешные controls сохранены.

Проверки реализации:

| Проверка | Результат |
| --- | --- |
| Новые регрессионные тесты до исправления | 15 failed, 30 assertions: исходные дефекты воспроизведены. |
| HydrationErrorContractTest + HydrationCompatibilityTest | 43 passed, 321 assertions. |
| Полный `composer test` | 1298 passed, 36 прежних deprecation, 4340 assertions, 6.14 s; падений нет. |
| `composer dump-autoload --optimize --strict-psr` | Успех. |
| `php tests/Support/standalone-json-smoke.php /path/to/no-dev-checkout` | Успех с текущими src и production dependencies; Illuminate Container и Guzzle HTTP Client отсутствуют. Проверены прежние JSON/unwrap/ID контракты, required nullable, строгий JsonCast и raw body без debug. |
| PHP lint | 27 затронутых/новых PHP-файлов, без ошибок. |
| Локальные Markdown-ссылки и `git diff --check` | Без ошибок. |

Для повторения новых тестов:

```bash
vendor/bin/pest tests/Unit/Serialization/HydrationErrorContractTest.php \
  tests/Unit/Serialization/HydrationCompatibilityTest.php
```

Согласованный объём D1–D3 выполнен. Весь AS-07/AS-10 не объявляется закрытым;
ограничения из раздела совместимости остаются за пределами этой поставки.
