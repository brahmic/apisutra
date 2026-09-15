# Проверка пользовательских маршрутов

Результат редакционной проверки реализации 027; опубликованные примеры выполняются
через `composer check-docs` и затем из обоих архивов без require-dev. Фикстуры учебные,
HTTP подменён MockTransport. Контекстные фрагменты справочника не объявляются
самостоятельными программами.

| Задача | Путь до результата | Проверенное завершение |
| --- | --- | --- |
| Создать SDK | [create-sdk](../../../docs/start/create-sdk.md) → analysis/design → [quickstart](../../../docs/guides/quickstart.md) → [Records SDK](../../../docs/example/sdk/README.md) → coverage/release | Конфигурация передана клиенту, resource создаёт запрос с этим клиентом, Returns даёт plain DTO и `_extra`; ошибка 404 видна в результате |
| Добавить операцию | [add-operation](../../../docs/start/add-operation.md) → request declaration → DTO → unit | Метод ресурса, путь, query/body, Returns и проверка ошибки названы явно; актуальный шаблон — файлы GetRecord |
| Описать plain DTO | [describe-dto](../../../docs/start/describe-dto.md) → plain-models → [пример правил](../../../docs/example/hydration-rules/README.md) | strict, fallback, одиночный объект, each/list, остаток данных, `_extra`, scoped itemCast и исходящий payload проверяются на одном опубликованном источнике |
| Описать атрибутный DTO | Тот же describe-dto → attribute-models → AttributeExample/RecordDto | `RecordDto::from()` выполняется на той же fixture, id/title совпадают с plain-моделью; отдельный маршрут ИИ не нужен |
| Использовать SDK | [use-sdk](../../../docs/start/use-sdk.md) → standalone/Laravel → results | Явная фабрика работает без Laravel; опубликованный binding отдельно проверен в Laravel 12, включая namespace registry и singleton |
| Настроить await | [рецепт continuation](../../../docs/guides/recipes/continuation.md) → state/await → [пример](../../../docs/example/continuation/README.md) | Pending на start и первом poll, Ready на втором; три HTTP-вызова, id=7, повторный await возвращает тот же DTO без HTTP |
| Разобрать strict-ошибку | [diagnose](../../../docs/start/diagnose.md) → dto diagnostics → results errors | Код `invalid_field_type`, DTO path `items[1].id`, sourcePath `/rows/1/value/record_id`; точный context и безопасный log разделены в reference |
| Работать над ApiSutra | [CONTRIBUTING](../../../CONTRIBUTING.md) → [development](../../../development/README.md) → .agents/workflow | Отдельный вход checkout: устройство, изменения, выбор проверок. Публичные casts/hooks/extensions доступны из пользовательского reference |

## Человек и агент

Для создания SDK обычный вход и [agent](../../../docs/start/agent.md) приводят к одному
`start/create-sdk.md`: объём → проектирование → сборка → запрос → DTO → тесты →
условные механизмы → покрытие/выпуск. Agent добавляет карточку задания и формат
передачи результата. Контракты, порядок и критерии не скопированы и не изменены.
Человеку не требуется читать agent-страницу, агенту — внутреннее устройство ядра.

## До и после

Раньше методология занимала 700 строк и перемешивала порядок работ, API, тесты,
auth и результаты. Теперь общий порядок занимает одну короткую страницу, а анализ,
проектирование, первая операция, покрытие и выпуск читаются по шагу задачи.
Quickstart больше не создаёт неиспользуемый config перед `app(Client::class)`.

Полные пересекавшиеся описания DTO/serialization в старых guide, атрибутном справочнике
и glossary сведены к владельцам: defaults, shapes, profiles, dto-output и receiver-output.
ClientConfig стал каталогом параметров со ссылками на поведение. Development описывает
внутренние связи, но ссылается на публичные гарантии вместо их повторной спецификации.
Словарь хранит определения; исторические имена не объявляются существующим API.

Лимиты строк/байтов проверены для каждой страницы, исключения по размеру не потребовались.
Время чтения не измерялось; оценка удобства основана на маршрутах и распределении
содержания, а не на неподтверждённом обещании ускорения.
