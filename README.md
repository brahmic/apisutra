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

ApiSutra — PHP-пакет для создания SDK внешних API. Вы описываете операции, DTO и правила протокола, а пакет выполняет запросы, преобразует ответы и управляет
авторизацией, повторными попытками и пагинацией. Приложение получает клиент с понятными операциями, типизированными данными и общей обработкой ошибок.

Нужны PHP 8.4+ и Composer. Ядро работает без приложения Laravel; для Laravel предусмотрена отдельная интеграция.

## Возможности

| Область | Что поддерживается |
| --- | --- |
| Конфигурация клиента | **Настройки клиента и отдельных вызовов.**<br>[Параметры ClientConfig](docs/reference/client/configuration.md), [настройка аутентификации](docs/reference/auth/strategies.md), [учётные данные](docs/reference/auth/credentials.md), [обновление токенов](docs/reference/auth/tokens.md#refresh-и-401), [копии конфигурации](docs/reference/client/configuration.md#создать-и-изменить), [опции запроса](docs/reference/request/declaration.md#runtimeопции-частые), [подключение контейнера](docs/reference/client/construction.md) |
| Операции SDK | **Организация API в удобный клиент.**<br>[Запросы](docs/reference/request/declaration.md), [ресурсы](docs/reference/client/resources.md), [версии сервисов](docs/reference/client/versioning.md), [поиск клиента](docs/reference/client/discovery.md), [мультисервисные SDK](docs/guides/integration/multi-service.md) |
| Транспорт | **Выбор HTTP-клиента и режима ответа.**<br>[Контракт транспорта](docs/reference/execution/transport.md#контракт), [promise API](docs/reference/execution/transport.md#promise-api-и-фактическое-выполнение), [форматы ответа](docs/reference/attributes/response.md), [внешние и подписанные URL](docs/reference/execution/transport.md#изоляция-назначения) |
| DTO | **Типизированные модели данных.**<br>[Атрибутные модели](docs/guides/dto/attribute-models.md), [обычные PHP-классы](docs/guides/dto/plain-models.md), [внешние правила](docs/reference/dto/field-rules.md), [типизированные коллекции](docs/reference/dto/collections.md) |
| Гидратация DTO | **Контроль формы и содержимого данных.**<br>[Маппинг и профили](docs/reference/dto/profiles.md), [строгие типы](docs/reference/dto/scalars.md), [null и defaults](docs/reference/dto/defaults.md), [вложенные структуры](docs/reference/dto/shapes.md), [варианты элементов списка](docs/reference/dto/variants.md), [неизвестные поля](docs/reference/dto/extras.md) |
| Атрибуты | **Декларации рядом с кодом.**<br>[HTTP](docs/reference/attributes/http.md), [параметры запроса](docs/reference/attributes/request.md), [DTO](docs/reference/attributes/hydration.md), [ответы](docs/reference/attributes/response.md), [поведение](docs/reference/attributes/behavior.md), [хуки](docs/reference/attributes/hooks.md) |
| Валидация | **Проверка входных данных до отправки.**<br>[Правила запросов и DTO](docs/reference/client/validation.md), [подключение валидатора](docs/reference/client/validation.md#как-подключается-валидатор), [oneOf и discriminator для body](docs/reference/request/declaration.md#oneof-и-discriminator-для-polymorphic-body) |
| Сериализация | **Подготовка данных для HTTP.**<br>[Части запроса](docs/reference/serialization/request-parts.md), [URI и query](docs/reference/serialization/uri-query.md), [тело запроса](docs/reference/serialization/body.md), [даты и enum](docs/reference/serialization/dto-output.md), [касты](docs/reference/serialization/casts.md), [исключение служебного поля DTO](docs/reference/serialization/receiver-output.md) |
| Результаты и ошибки | **Общий способ работы с ответами.**<br>[Типизированные результаты](docs/reference/results/handles.md), [ошибки и исключения](docs/reference/results/errors.md), [контекст ошибок](docs/reference/results/errors.md#errorcontextfactory) |
| Диагностика | **Поиск причин неожиданного поведения.**<br>[Логи](docs/reference/results/observability.md#logger-и-loglevel), [traceId](docs/reference/results/observability.md#traceid), [debug-снимки](docs/reference/results/observability.md#debug), [маскирование данных](docs/reference/results/observability.md#маскирование-безопасного-экспорта), [пути ошибок DTO](docs/reference/dto/diagnostics.md) |
| Авторизация | **Доступ к защищённым API.**<br>[Стратегии](docs/reference/auth/strategies.md), [credentials](docs/reference/auth/credentials.md), [обновление токенов](docs/reference/auth/tokens.md) |
| Управление отправкой | **Контроль нагрузки и времени выполнения.**<br>[Повторные попытки](docs/reference/execution/retry.md), [квоты](docs/reference/execution/rate-limit.md), [дедлайны](docs/reference/execution/deadlines.md), [кеширование](docs/reference/execution/cache.md) |
| Несколько запросов | **Выполнение связанных и массовых операций.**<br>[Пагинация](docs/reference/execution/pagination.md), [batch и pool](docs/reference/execution/batch-pool.md), [композиция и зависимости](docs/reference/request/composition.md) |
| Длительные операции | **Получение отложенного результата API.**<br>[Критерий готовности](docs/reference/execution/continuation-state.md), [режимы операции](docs/reference/execution/continuation-state.md#режим-provider-выполнения), [ожидание и polling](docs/reference/execution/continuation-await.md) |
| Файлы | **Потоковая передача файлов и работа с архивами.**<br>[Загрузка multipart/binary и Base64 в JSON](docs/reference/files/uploads.md#форматы-загрузки), [скачивание в файл или поток](docs/reference/files/downloads.md#сохранение-сразу-в-путь-или-поток), [файловые поля DTO](docs/guides/dto/showcase.md#файл-в-поле-dto), [чтение и распаковка архивов](docs/reference/files/archives.md#архивы), [запускаемый пример](docs/guides/recipes/files.md) |
| Расширения | **Подключение собственного поведения.**<br>[Хуки](docs/reference/extensions/hooks.md), [обработчики ответов](docs/reference/extensions/extensions.md#response-handlers-и-приоритет), [модули расширений](docs/reference/extensions/extensions.md), [контекст вложенной гидратации](docs/reference/dto/scope.md) |
| Каталоги SDK | **Описание операций и типов для инструментов.**<br>[Инвентаризация операций](docs/reference/client/operation-inventory.md), [каталог DTO ответов](docs/reference/client/response-dto-catalog.md), [справочники провайдера](docs/reference/client/catalogs.md) |
| Тестирование SDK | **Проверка сценариев и контрактов API.**<br>[Mock-ответы](docs/reference/testing/mocking.md), [фикстуры](docs/reference/testing/fixtures.md), [live-проверки](docs/reference/testing/live.md) |
| Интеграции | **Подключение к окружению приложения.**<br>[Standalone](docs/guides/integration/standalone.md), [Laravel](docs/guides/integration/laravel.md), [Redis для общих квот](docs/reference/integrations/redis.md) |

## Как выглядит SDK

Импорты в обзорных фрагментах опущены:

### Декларация запроса

[GetRecordRequest](docs/example/sdk/src/Resources/Records/Get/GetRecordRequest.php) задаёт операцию API:

```php
// HTTP-метод и адрес операции.
#[Get('/records/{id}')]
// Повторы при временных ошибках API: до 3 попыток, включая первую.
#[Retry(attempts: 3)]
// Преобразовать содержимое поля data в типизированный DTO.
#[Returns(GetRecordResponseDto::class, unwrap: 'data')]
final class GetRecordRequest extends AbstractRequest
{
    public function __construct(
        // Подставить id в {id} адреса запроса.
        #[Path]
        public int $id,
    ) {
    }
}
```

### Декларация ответа (DTO)

[GetRecordResponseDto](docs/example/sdk/src/Resources/Records/Get/GetRecordResponseDto.php) описывает данные, которые получит приложение:

```php
// Типизированная модель записи, которую получит приложение.
final readonly class GetRecordResponseDto extends AbstractResponseDto
{
    public function __construct(

        #[From('record_id', fallback: ['id'])] // Если record_id отсутствует, взять id.
        public int $id,

        public string $title,

        // Преобразовать строку created_at из ответа API в объект даты.
        #[From('created_at')]
        #[DateTimeFrom(format: DATE_ATOM)]
        public DateTimeImmutable $createdAt,

        #[From('author.name')] // Прочитать имя из вложенного объекта author.
        public ?string $authorName = null,

        #[EmptyStringAsNull(blank: true)] // Пустую строку и пробелы превратить в null.
        public ?string $description = null,

        // Неизвестные поля ответа; сбор включается через extras('_extra') ниже.
        public array $_extra = [],
    ) {
    }
}
```

`_extra` нужно только для сохранения неизвестных полей: свойство объявляется явно, а правило `extras('_extra')` включает его заполнение. Без сбора уберите оба.

[Возможности DTO на одном примере](docs/guides/dto/showcase.md) — вложенные модели, коллекции, enum, casts, defaults, исходящий JSON и ошибки.

### Создание и конфигурирование клиента

Подключите [правила DTO](docs/example/sdk/src/Config/HydrationRulesFactory.php) к [клиенту](docs/example/sdk/src/DemoClient.php):

```php
$rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
    ->withDto(GetRecordResponseDto::class, DtoRules::create()->extras('_extra'));

$client = new DemoClient(
    new ClientConfig(
        baseUrl: 'https://api.example.test',
        timeout: 15, // Таймаут HTTP-запроса в секундах.
        hydrationRules: $rules,
    ),
    HttpTransport::createDefault(),
);

/** @var GetRecordResponseDto $record */
$record = $client->records()->get(7)->send()->dataOrFail();
echo $record->createdAt->format('d.m.Y'); // 15.09.2026
```

`$record` — типизированный `GetRecordResponseDto`. `dataOrFail()` возвращает DTO или выбрасывает исключение; доступна и [явная проверка результата](docs/reference/results/handles.md).

[Возможности конфигурирования клиента](docs/guides/client/showcase.md) · [Подключить свой API](docs/guides/integration/standalone.md#настоящий-http).

## Установка и первый запуск

Пакет находится на стадии **alpha**. Установите его и запустите учебный SDK без ключей API и сетевых запросов:

```bash
composer require "brahmic/apisutra:^0.2@alpha"
php vendor/brahmic/apisutra/docs/example/sdk/run.php
```

[Быстрый старт](docs/guides/quickstart.md) объясняет пример и переход к своему API.

## Документация

- [Создать SDK](docs/start/create-sdk.md) — от анализа API до проверенной операции и покрытия.
- [Использовать готовый SDK](docs/start/use-sdk.md) — подключение к приложению и работа с результатами.
- [Справочник](docs/reference/README.md) — настройки, контракты, приоритеты и ограничения.
- [Примеры](docs/example/README.md) — готовый код для локального запуска.

[Все разделы и задачи](docs/README.md) · [Передать задачу ИИ-агенту](docs/start/agent.md).

## Разработка ApiSutra

[Точка входа разработчика ApiSutra](https://github.com/brahmic/apisutra/blob/master/docs/development/README.md)
задаёт общий маршрут для человека и ИИ: окружение, архитектура, устройство пакета и проверка изменений.
Подготовка вклада — в [CONTRIBUTING](https://github.com/brahmic/apisutra/blob/master/CONTRIBUTING.md).

## Версии и лицензия

[История изменений](CHANEGLOG.md) · [Миграция](docs/migration/README.md) · [Лицензия MIT](LICENSE).
