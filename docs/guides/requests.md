# Описать запрос операции

Запрос хранит параметры одной операции. Клиент задаёт общую конфигурацию, ресурс
создаёт запрос с привязкой к нему, `send()` выполняет операцию.

## Начать с работающего примера

[GetRecordRequest](../example/sdk/src/Resources/Records/Get/GetRecordRequest.php)
объявляет GET `/records/{id}`, параметр Path и `Returns` с unwrap.
[RecordsResource](../example/sdk/src/Resources/Records/RecordsResource.php) создаёт
запрос, а [run.php](../example/sdk/run.php) отправляет его через fake.

1. Сопоставьте каждое входное поле с path, query, header, body или file.
2. Задайте метод и endpoint, затем DTO результата и путь внутри envelope.
3. Проверьте подготовленный HTTP-запрос на локальной фикстуре.
4. Добавьте негативную проверку входа и ошибочный ответ API.

## Выбрать декларацию

[Справочник запроса](../reference/request/declaration.md) описывает convention,
body DTO, BodyRoot, oneOf/discriminator и runtime-опции. Параметры атрибутов находятся
в [каталоге](../reference/attributes/request.md), порядок сборки — в
[request parts](../reference/serialization/request-parts.md).

Для повторяющегося payload используйте DTO; для корневого списка JSON Patch — BodyRoot.
Массив в query и JSON-строка в одном параметре имеют разные способы кодирования:
[URI/query](../reference/serialization/uri-query.md).

## Проверить результат

Запрос должен отправлять только объявленные данные, не выполнять I/O при локально
неверном входе и возвращать DTO либо объяснимую ошибку. Результат читается через
[ResultHandle/resolved](../reference/results/handles.md).

Добавление операции целиком — [отдельный маршрут](../start/add-operation.md).
