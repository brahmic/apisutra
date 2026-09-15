# Учебный Records SDK

Небольшой SDK условного сервиса записей. В нём одна операция, DTO с атрибутами
маппинга и даты, запасной ключ, чтение вложенного значения и нормализация пустой строки.
Внешние правила включают строгие типы и сбор неизвестных полей.
Локальные JSON-ответы показывают успешный результат и HTTP 404.

## Запуск

Из checkout ApiSutra после `composer install`:

```bash
php docs/example/sdk/run.php
```

Из приложения с установленным пакетом:

```bash
php vendor/brahmic/apisutra/docs/example/sdk/run.php
```

Ожидаемый результат:

```json
{"id":7,"title":"Первая запись","createdAt":"2026-09-15T10:30:00+00:00","authorName":"Анна","description":null,"extra":{"future_flag":false},"failed":true,"status":404}
```

## Исходники

| Файл | Назначение |
| --- | --- |
| [bootstrap.php](bootstrap.php) | Автозагрузка пространства имён примера |
| [run.php](run.php) | Явная сборка, два вызова и чтение результата |
| [DemoClient](src/DemoClient.php) | Вход `records()` |
| [ClientConfigFactory](src/Config/ClientConfigFactory.php) | URL и набор правил клиента |
| [HydrationRulesFactory](src/Config/HydrationRulesFactory.php) | Строгие типы и сбор неизвестных полей в `_extra` |
| [RecordsResource](src/Resources/Records/RecordsResource.php) | Создание привязанного запроса |
| [GetRecordRequest](src/Resources/Records/Get/GetRecordRequest.php) | GET, параметр пути, типизированный ответ и повторы при временных ошибках |
| [GetRecordResponseDto](src/Resources/Records/Get/GetRecordResponseDto.php) | Наследник AbstractResponseDto: From с fallback и вложенным путём, DateTimeFrom, EmptyStringAsNull |
| [RecordDto с атрибутом](src/AttributeExample/RecordDto.php) | Создание DTO через from() без клиента и внешних правил |
| [Laravel provider](src/Laravel/DemoServiceProvider.php) | Явный binding с конфигурацией SDK |
| [Успех](fixtures/record.json), [ошибка](fixtures/error.json) | Обезличенные локальные ответы |

`run.php` не загружает Laravel и не выполняет настоящий HTTP. Его запускают проверки
документации и оба архива поставки; тест не содержит отдельной копии учебного кода.
Laravel provider проверяется отдельно в приложении Laravel.

## Использовать как основу

Скопируйте нужные классы в namespace своего SDK и зарегистрируйте PSR-4 в Composer.
Замените фикстуры подтверждёнными ответами API. [Quickstart](../../guides/quickstart.md)
объясняет путь исполнения, а [создание SDK](../../start/create-sdk.md) задаёт полный
порядок расширения. Для реального транспорта следуйте
[standalone-подключению](../../guides/integration/standalone.md).
