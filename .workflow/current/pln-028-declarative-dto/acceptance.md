# Приёмка внешних правил гидратации DTO

- Дата создания: 2026-09-14
- Дата обновления: 2026-09-14

Контракт — [contracts.md](contracts.md). Сейчас ни одна строка не выполнена.
Исходное состояние — [readiness](readiness.md) на `eeb0b6a`; сохранённые probes
aud-006 и dsc-005 описывают старое поведение и не переписываются.

Каждая проверка выполняется через standalone `Hydrator::forRules()` и через Returns
с MockTransport, если строка не ограничивает вход. Сравниваются DTO, reason, DTO-путь,
sourcePath, число вызовов конструктора и отсутствие HTTP retry. На каждый пункт
[матрицы аудита](../../audit/aud-006-declarative-dto/criteria.md) нужна строка ниже
или ссылка на достаточный существующий тест.

## Порция A: правила, strict и форма

| ID | Требование | Сценарий | Ожидаемый результат |
| --- | --- | --- | --- |
| A01 | AS-3.1–3.2 | Сквозной граф UC-01 без атрибутов, standalone и Returns | Одинаковые DTO и ошибки |
| A02 | AS-3.4 | Два набора для одного графа, перестановка вызовов, metadata cache on | Результаты не зависят от порядка и прогрева |
| A03 | AS-3.4 | Клиент с набором, клиент без набора, `DTO::from()` | Поведение последних двух не меняется |
| A04 | В01 | `ClientConfig::with()` без override, с новым набором, с `null` | Набор перенесён, заменён, отключён |
| A05 | В01 | Ошибки описания: повтор класса, повтор группы, два преобразования, объект, отличный от enum case, в `HandlerSpec` и внутри массива `DefaultSpec::value` | `ConfigurationException` при сборке или компиляции |
| A06 | AS-3.5, В02 | `FieldRule` и каждый из семи входных атрибутов на одном поле | `ConfigurationException` до данных |
| A07 | В02 | Атрибут на другом поле; `To`/`DateTimeTo`; посторонний атрибут | Атрибутное поле работает; конфликтов нет |
| A08 | В02 | `DtoRules` у класса с DtoHydrate или унаследованным профилем | `ConfigurationException` |
| A09 | В02 | Класс с профилем без `DtoRules` при strict defaults набора | Прежнее разрешение 029, defaults не применены |
| A10 | В02 | Unspecified/false/пустые fallback/default null/`noTransform()` | Различаются согласно contracts.md |
| A11 | В03 | Один дочерний класс в двух ветках; отключение strict у родителя | Одна схема класса; strict ребёнка не отключён |
| A12 | В03 | Plain-дочерний объект без `dto()` | Не превращается в DTO неявно |
| A13 | В04 | Stateful `HandlerSpec`-cast на соседних элементах и в двух клиентах | Состояние не делится |
| A14 | В04 | Scoped cast/provider через `HandlerSpec`, property Cast, `Nested(itemCast:)`, DefaultValue, профиль, casts набора | Scoped-метод получает scope того же гидратора и набора |
| A15 | В04 | RowCast передаёт scope помощнику; помощник вызывает `Hydrator::default()` | С scope набор применяется; с default — задокументированная граница |
| A16 | AS-1, В05 | Таблица AS-1 и строки contracts.md для int/float/bool/string/literal/union/mixed | Точное соответствие таблице |
| A17 | В05 | ±(2^53−1), ±2^53, ±(2^53+1), ±(2^53+2), PHP_INT_MIN/MAX, JSON `1` и `1.0` | Расширение только в диапазоне; overflow — `integer_out_of_range` |
| A18 | AS-1, В05 | Cast возвращает верный тип, строку для int, int для float | Строка отклонена, int → float в диапазоне принят |
| A19 | AS-1, В06 | `list(int)`: `[1,2]`, `[1,"2"]`, `[1,null]`, `[]`; `list(nullable(int))` | Ошибки на `ids[1]`; nullable принимает null |
| A20 | В06 | `list(list(string))` с ошибкой во второй строке | Путь с обоими индексами |
| A21 | AS-4 | Optional `?int = null` с `forbidExplicitNull()`: `{}`, `7`, `null`, `"7"` | null, 7, `explicit_null_not_allowed`, strict-ошибка |
| A22 | AS-4, В07 | Keep/NullIfEmpty/EmptyStringAsNull/Cast × missing/null/`""` | Таблица contracts.md |
| A23 | AS-4 | `required()` typed collection с default, Missing | `required_field_missing` |
| A24 | AS-4 | `list()` со словарём, разреженным массивом, пустым массивом; `normalizeKeys` | Форма отклонена до переиндексации; нормализация сохраняет ключи |
| A25 | В07 | `dto()` с непустым list и с пустым массивом | Непустой list отклонён; пустой массив проходит проверку формы |
| A26 | В08 | `cast()` с `result`, готовый DTO от itemCast, each → itemCast → DTO | Одна гидратация, счётчик конструктора 1 |
| A27 | В08 | Атрибутный Nested + Cast без внешнего правила | Прежнее игнорирование Cast |
| A28 | AS-общ., В09 | Known valid/invalid, unknown, missing discriminator; Value/Key; KeepRaw/Skip/Error | Повреждённый известный — ошибка; unknown по политике |
| A29 | В09 | KeepRaw для typed collection DTO | `ConfigurationException` |
| A30 | В16 | Returns sync/promise, unwrap, пагинация items-only и контейнер, CompositeFlow | Правила набора применены |
| A31 | В16 | HTTP cache hit двумя клиентами с разными наборами | Каждый ответ гидратирован своим набором |
| A32 | В16 | Response handler, RawResponse, Download | Отдельные ветки без гидратации набором |
| A33 | В16, 030 | Await после 030: Ready с неверным strict-типом, token есть | `final_hydration_failed` без следующих попыток |
| A34 | В17 | Рекурсивный `Node → Node`, глубина 512/513, цикл PHP-объектов, общий объект в двух ветках | Дерево гидратируется; `hydration_depth_exceeded`; `cyclic_hydration_input`; общий объект допустим |
| A35 | AS-общ. | Без Laravel и сети; установка без dev-зависимостей | Illuminate не загружается |
| A36 | В04, 031 | Provider, создающий объект, и `DefaultSpec::value` с массивом значений; два применения одного набора | Provider даёт независимые объекты; literal-массив одинаков и не содержит объектов, кроме enum case |
| A37 | В01 | `DefaultSpec::value(Status::Ready)` и `DefaultSpec::value(['nested' => [Status::Ready]])`; unit и backed enums, Missing, два применения одного набора | Декларации приняты; сохраняется тот же enum case (`===`) отдельно и внутри массива, без преобразования в scalar |

## Порция B: дополнительные поля

| ID | Требование | Сценарий | Ожидаемый результат |
| --- | --- | --- | --- |
| B01 | AS-2.1 | `{"record_id":7,"active":false,"future":null}`, mapping id и active | `extra = ["future" => null]` |
| B02 | AS-2.2 | false, 0, `""`, `[]`, вложенные данные; receiver на двух уровнях | Всё сохранено; у каждого DTO свой остаток |
| B03 | AS-2.3, В10 | Каждая строка таблицы остатка, включая перестановку полей | Таблица contracts.md |
| B04 | В10 | each + meta; двойные проекции; плотные 0/1, единственный 1, отсутствие остатков, словарь с normalizeKeys | Плотные записи sourceKey/remainder; JSON-форма стабильна |
| B05 | В10 | Ключи `"1"` и `"01"` после JSON-декодирования | sourceKey 1 (int) и "01" (string) |
| B06 | В10 | Value-тег с сопоставлением и без; Key-обёртка с meta; KeepRaw; Skip | Распределение по таблице contracts.md |
| B07 | AS-2.4, В11 | Ключ источника с именем receiver | Сохранён внутри остатка |
| B08 | В11 | Promotion, required/optional параметр, класс без конструктора | Receiver передан в единственный вызов конструктора |
| B09 | В11 | Static/virtual/non-public/неверный тип/конструктор без параметра/атрибут или `FieldRule` на receiver | `ConfigurationException` |
| B10 | В10 | Исходный массив после гидратации | Не изменён |
| B11 | AS-2.5 | Отсутствие receiver | Прежнее поведение |
| B12 | AS-2.5, В12 | Клиент с набором: `DtoInterface` и plain DTO с receiver как BodyRoot, свойство Body, multipart-body и query; вложенный DTO; список DTO | Receiver отсутствует, не развёрнут в корень; остальные поля без изменений; header, path и file ведут себя как до 028 |
| B13 | В12 | Тот же DTO через `toArray()` и DtoSerializer без набора | `extra` под своим именем |
| B14 | В12 | Клиент без набора; набор без `extras()` для класса | Прежняя сериализация свойства |
| B15 | В12 | Вручную созданный DTO с заполненным receiver в запросе клиента с набором | Receiver исключён так же, как у гидратированного |
| B16 | В12 | `To`, `DateTimeTo`, `Query`, `Body`, `BodyRoot`, `Header`, `Path`, `File` на receiver | `ConfigurationException` при компиляции |
| B17 | В12 | Prepared request, `requestDebug()`, ключ HTTP cache, лог запроса | Представление без receiver |
| B18 | В12 | Plain DTO с receiver и соседними DateTime, enum, вложенным массивом, пустым `stdClass` | JSON остальных полей совпадает с сериализацией того же объекта без набора |
| B19 | В12 | Класс, у которого после исключения receiver не осталось свойств | Пустой JSON-объект |
| B20 | В12 | Массив и plain-обёртка без receiver, содержащие DTO с receiver; DTO с receiver на третьем уровне | Заменены только контейнеры на пути; receiver исключён на всех уровнях |
| B21 | В12 | `#[Cast(JsonCast::class)]` на BodyRoot, Body и query-свойстве с DTO с receiver; cast по типу из `ClientConfig::casts` для класса с receiver | `SerializationException` до вызова cast; HTTP-запрос не отправлен; `serialization_error` в результате |
| B22 | В12 | Класс с `extras()` реализует `JsonSerializable` или `Stringable` либо имеет `toArray()` без `DtoInterface` | `ConfigurationException` при компиляции набора |
| B23 | В12 | Исходный DTO после сериализации; счётчик конструктора; набор без receiver | Объект не изменён, конструктор не вызван; без receiver в наборе обхода нет и представление прежнее |

## Порция C: происхождение и безопасный лог

| ID | Требование | Сценарий | Ожидаемый результат |
| --- | --- | --- | --- |
| C01 | AS-3.6, В13 | unwrap=data, rows, each=value, record_id, ошибка второго элемента | path `data.items[1].id`, sourcePath `/data/rows/1/value/record_id`, Resolved |
| C02 | В13 | Fallback выбран; primary null | sourcePath указывает найденный alias |
| C03 | В13 | Missing с кандидатами | Expected, primary и `sourceCandidates` |
| C04 | В13 | Ключи с `.`, `[`, `/`, `~`; пустой корень | Корректное экранирование JSON Pointer |
| C05 | В13 | Skip и normalizeKeys | sourcePath исходного элемента, DTO-путь с порядковым индексом |
| C06 | В14 | Hook переименовал/создал/удалил поле; JsonCast; provider Missing/Present | Boundary или Unavailable, никогда не Resolved |
| C07 | В15 | Секрет в строковом и числовом ключе | Точный путь в `context()` и результате; нет в message и автоматическом логе |
| C08 | В15 | Ошибка после unwrap, previous, `debug=false` | Маскирование сохраняется |
| C09 | В13 | Гидратор без набора | Прежний контекст ошибки без новых полей |

## Команды и завершение

```bash
vendor/bin/pest tests/Unit/Serialization tests/Unit/DataTransfer tests/Unit/Result tests/Unit/Pipeline --compact
composer test
composer lint
composer analyse
composer check-docs
composer dump-autoload --optimize --strict-psr
composer check-package -- --staged
```

Порция принимается, когда её строки пройдены и результаты с commit сохранены в `artifacts/`.
План завершается после приёмки порций A, B и C.
