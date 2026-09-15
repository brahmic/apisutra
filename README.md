<p align="center">
  <img src="docs/assets/apisutra-logo.png" alt="Логотип ApiSutra" width="233">
</p>

<h1 align="center">ApiSutra</h1>

<p align="center">Декларативный SDK для создания API-клиентов</p>

<p align="center">
  <a href="https://github.com/brahmic/apisutra/actions/workflows/tests.yml"><img src="https://github.com/brahmic/apisutra/actions/workflows/tests.yml/badge.svg?branch=master&amp;event=push" alt="Tests"></a>
  <a href="https://github.com/brahmic/apisutra/actions/workflows/docs.yml"><img src="https://github.com/brahmic/apisutra/actions/workflows/docs.yml/badge.svg?branch=master&amp;event=push" alt="Docs CI"></a>
  <a href="docs/README.md"><img src="https://img.shields.io/badge/docs-read-2563eb" alt="Documentation"></a>
  <a href="composer.json"><img src="https://img.shields.io/badge/PHP-8.4%2B-777BB4" alt="PHP 8.4+"></a>
  <a href="https://packagist.org/packages/brahmic/apisutra"><img src="https://img.shields.io/packagist/vpre/brahmic/apisutra" alt="Packagist"></a>
</p>

ApiSutra — PHP-пакет для создания SDK внешних API. Вы описываете операции, DTO
и правила протокола, а пакет выполняет запросы, преобразует ответы и управляет
авторизацией, повторными попытками и пагинацией. Приложение получает клиент
с понятными операциями, типизированными данными и общей обработкой ошибок.

Нужны PHP 8.4+ и Composer. Ядро работает без приложения Laravel;
для Laravel предусмотрена отдельная интеграция.

## Возможности

| Область | Что поддерживается |
| --- | --- |
| Операции SDK | [Декларативные запросы](docs/reference/request/declaration.md), [ресурсы](docs/reference/client/resources.md) и [версии сервисов](docs/reference/client/versioning.md) |
| DTO | [Атрибутные модели](docs/guides/dto/attribute-models.md) и [внешние правила](docs/reference/dto/field-rules.md): строгие типы, вложенные объекты, коллекции и сохранение неизвестных полей |
| Атрибуты | [Декларации HTTP, параметров запроса, преобразований DTO, ответов, поведения и хуков](docs/reference/attributes/README.md) |
| Сериализация | [Path, query, headers и body](docs/reference/serialization/README.md), преобразование значений через casts |
| Результаты и ошибки | [Типизированные ответы, проверка ошибок и исключения](docs/reference/results/handles.md), [диагностика](docs/reference/results/observability.md) |
| Авторизация | [Стратегии, credentials и обновление токенов](docs/reference/auth/README.md) |
| Управление отправкой | [Retry](docs/reference/execution/retry.md), [квоты](docs/reference/execution/rate-limit.md), [общий deadline](docs/reference/execution/deadlines.md) и [кеш ответов](docs/reference/execution/cache.md) |
| Несколько запросов | [Пагинация](docs/reference/execution/pagination.md), [batch/pool](docs/reference/execution/batch-pool.md), [композиция и зависимости](docs/reference/request/composition.md) |
| Длительные операции | [Проверка готовности и ожидание результата через polling](docs/reference/execution/continuation-state.md) |
| Файлы | [Потоковая загрузка и скачивание, работа с архивами](docs/reference/files/README.md) |
| Расширения | [Хуки и подключаемые обработчики](docs/reference/extensions/README.md) |
| Тестирование SDK | [Mock-ответы, фикстуры и проверки без внешнего API](docs/reference/testing/README.md) |
| Интеграции | [Laravel](docs/guides/integration/laravel.md), [Redis для общих квот](docs/reference/integrations/redis.md) |

## Как выглядит SDK

Автор SDK объявляет операцию и модель ответа. Ниже фрагменты
[GetRecordRequest](docs/example/sdk/src/Resources/Records/Get/GetRecordRequest.php) и
[GetRecordResponseDto](docs/example/sdk/src/Resources/Records/Get/GetRecordResponseDto.php)
из учебного SDK:

```php
use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Path;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/records/{id}')]
#[Returns(GetRecordResponseDto::class, unwrap: 'data')]
final class GetRecordRequest extends AbstractRequest
{
    public function __construct(
        #[Path]
        public int $id,
    ) {
    }
}

final readonly class GetRecordResponseDto
{
    public function __construct(
        public int $id,
        public string $title,
        public array $_extra = [],
    ) {
    }
}
```

В этом примере DTO — обычный PHP-класс. Подключённый к клиенту
[набор правил](docs/example/sdk/src/Config/HydrationRulesFactory.php) сопоставляет
`record_id` с `id`, проверяет типы и сохраняет неизвестные поля в `_extra`.

В приложении используется настроенный `$client` типа
[DemoClient](docs/example/sdk/src/DemoClient.php):

```php
$record = $client->records()->get(7)->send()->dataOrFail();
echo $record->title; // Первая запись
```

`dataOrFail()` возвращает данные или выбрасывает исключение. Для явной проверки
ошибок используйте [представление результата](docs/reference/results/handles.md).
Полная сборка клиента, транспорт и обработка ошибки показаны в
[запускаемом примере](docs/example/sdk/run.php).

## Установка и первый запуск

Пакет находится на стадии **alpha**. Установка в проект:

```bash
composer require "brahmic/apisutra:^0.2@alpha"
```

Попробовать учебный SDK без ключей API и сетевых запросов:

```bash
php vendor/brahmic/apisutra/docs/example/sdk/run.php
```

Команда использует локальные ответы через `MockTransport`.
[Quickstart](docs/guides/quickstart.md) объясняет пример и переход к своему API.
Для настоящего HTTP подключите [транспорт с его зависимостями](docs/reference/execution/transport.md).

## Документация

- [Создать SDK](docs/start/create-sdk.md) — от анализа API до проверенной операции и покрытия.
- [Использовать готовый SDK](docs/start/use-sdk.md) — подключение к приложению и работа с результатами.
- [Справочник](docs/reference/README.md) — настройки, контракты, приоритеты и ограничения.
- [Примеры](docs/example/README.md) — готовый код для локального запуска.

[Все разделы и задачи](docs/README.md) · [Передать задачу ИИ-агенту](docs/start/agent.md).

## Разработка ApiSutra

[Точка входа разработчика ApiSutra](https://github.com/brahmic/apisutra/blob/master/docs/development/README.md)
задаёт общий маршрут для человека и ИИ-агента: подготовка окружения, выбор механизма,
изменение кода и документации, проверки. В этом же разделе находятся архитектура
и устройство пакета. Подготовка вклада описана в
[CONTRIBUTING](https://github.com/brahmic/apisutra/blob/master/CONTRIBUTING.md).

## Версии и лицензия

[История изменений](CHANEGLOG.md) · [Миграция](docs/migration/README.md) · [Лицензия MIT](LICENSE).
