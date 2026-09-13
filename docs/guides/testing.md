# Тестирование

ApiSutra предоставляет базовые testing primitives: `fake`, фикстуры, assert‑методы
и несколько framework-agnostic helper-классов.

Важно:
- этот гайд описывает именно возможности core-пакета;
- архитектура live-suite конкретного SDK (support-слой, структура папок, Makefile, дампы, preflight и т.д.) строится на стороне SDK-провайдера;
- live-стратегия зависит от провайдера и не является обязательной частью каждого SDK.

## Как читать этот гайд

Для provider SDK на ApiSutra полезно держать в голове 4 уровня:
- базовый минимум — почти обязателен для любого SDK;
- рекомендуется по умолчанию — обычно окупается уже на реальном production SDK;
- нужен при определённых условиях — включается по признакам API;
- операторское удобство — не меняет core-контракт, но заметно упрощает сопровождение.

Ниже описаны именно core primitives. Но если SDK не игрушечный, а работает с
реальным API, то `record/playback`, live env gating, live assertions и contract helper
на практике чаще стоит считать нормальным baseline, а не редким дополнением.

## Быстрый fake
```php
use Brahmic\ApiSutra\Testing\MockResponse;

$client->fake([
    GetUser::class => MockResponse::success(['id' => 1, 'name' => 'Alice']),
]);

$client->preventStrayRequests();
```

Можно мокать по паттерну URL:
```php
$client->fake([
    'https://api.example/users/*' => MockResponse::success([]),
    '*' => MockResponse::notFound(),
]);
```

Приоритет поиска: сначала точное совпадение по классу запроса,
затем паттерны URL, затем `*` (fallback).

### Большие идентификаторы в fake и фикстурах

Для проверки большого числового литерала передавайте raw JSON:

```php
use Brahmic\ApiSutra\Testing\MockResponse;

$response = MockResponse::make('{"id":9223372036854775808}');
```

PHP float в массиве мог потерять цифры ещё до вызова MockResponse. RecordingTransport,
playback и безопасная диагностика сохраняют точные цифры больших целых; в записанном
JSON они могут стать строками. Raw HTTP-ответ при записи не изменяется. Маскирование
секретов сохраняется, байтовое равенство фикстуры с исходным телом не гарантируется.
Ранее округлённые значения в старых фикстурах нужно восстановить из точного источника.

## Файловые ответы

Для `#[Download]` используйте `MockResponse::file('/path/to/fixture.bin')`.
Каждый вызов открывает новую ручку, поэтому закрытие предыдущего `FileResponse`
не повреждает следующую попытку или тест. Поддерживаются status и headers,
а также включение файлового ответа в `MockResponse::sequence()`.

Recorder не читает upload/download поток ради fixture. Он записывает метаданные
и `bodyOmitted: true`; такая запись не содержит полного тела. Playback явно
отклоняет её и предлагает файловую fixture, вместо успешного пустого ответа.
Существующие JSON fixtures сохраняют прежнее поведение.

## Последовательности ответов
```php
$client->fake([
    GetUser::class => MockResponse::sequence([
        MockResponse::success(['id' => 1]),
        MockResponse::success(['id' => 2]),
    ]),
]);
```

## Динамические ответы
Ответ может быть callable:
```php
$client->fake([
    GetUser::class => function (GetUser $request) {
        return MockResponse::success(['id' => $request->id]);
    },
]);
```

## Ассерты
```php
$client->assertSent(GetUser::class);
$client->assertNotSent(DeleteUser::class);
$client->assertNothingSent();
```

## Фикстуры (record/playback)

Recorder маскирует стандартные credentials по умолчанию, даже без Fixture.
`$client->record()` использует `ClientConfig::redaction`; правила пользовательского
Fixture дополняют базовые. Настройка и границы маскирования — в
[логировании](logging.md#маскирование-безопасного-экспорта).

```php
use Brahmic\ApiSutra\Testing\Fixture;

final class UserFixture extends Fixture
{
    protected function defineSensitiveHeaders(): array
    {
        return ['Authorization' => '***'];
    }
}

$client->record(__DIR__ . '/fixtures', [
    GetUser::class => new UserFixture(),
]);

$client->playback(__DIR__ . '/fixtures');
```

В фикстурах можно скрывать:
- заголовки
- JSON‑поля
- regex‑паттерны

По умолчанию, если фикстуры отсутствуют, запрос считается не смоканным
и вернётся `MockResponse::notFound()` (если не включён `preventStrayRequests()`).

Если фикстуры отсутствуют, можно включить строгий режим:
```php
use Brahmic\ApiSutra\Testing\MockConfig;

MockConfig::throwOnMissingFixtures();
```

В строгом режиме отсутствие фикстуры приводит к исключению.

## Глобальный MockClient
```php
use Brahmic\ApiSutra\Testing\MockClient;

MockClient::global([GetUser::class => MockResponse::success()]);
// ...
MockClient::destroyGlobal();
```

## Helper для oneOf-контрактов
Для тестов SDK-провайдеров доступен framework-agnostic helper:
`Brahmic\ApiSutra\Testing\RequestContractTestHelper`.

Пример:
```php
use Brahmic\ApiSutra\Testing\RequestContractTestHelper;

$result = $request->send()->raw();

$isContractError = RequestContractTestHelper::isRequestContractViolation($result);
$context = RequestContractTestHelper::context($result);
$codes = RequestContractTestHelper::violationCodes($result);
$hasMismatch = RequestContractTestHelper::hasViolationCode($result, 'discriminator_mismatch');
```

Helper не зависит от PHPUnit/Pest и подходит для обоих стилей тестирования.

Практическое правило:
- если SDK использует `RequestOneOf` / `RequestDiscriminator`, helper почти всегда стоит считать baseline;
- если взаимоисключающих payload-вариантов нет, этот слой не нужен.

## Проверка валидации

Тест с `#[Validate]` должен явно предоставлять рабочую фабрику или проверять
`configuration_error` при её отсутствии. Для нескольких клиентов проверяйте порядок
A→B→A и разные сообщения/правила: фабрика одного клиента не должна заменять другую.
Без объявленных правил и для custom-only preflight Illuminate не требуется.
Если тест использует `Validator::useFactory()`, очищайте общую фабрику через
`Validator::resetFactory()` при завершении теста; reset реестра контейнера её не очищает.
[Приоритеты и различие ошибок](validation.md#приоритет-фабрики).

## Проверка контрактов DTO

Для ошибок данных проверяйте `hydration_error` и структурированные
`reason/path/expected/actual`, а не прежний текст PHP-исключения. В прямом вызове
ожидается `HydrationException`; `raw()`/`resolved()` возвращают ошибку,
`dataOrFail()`/`throwOnErrors` выбрасывают исключение. Проверяйте доступность
исходного HTTP body при `debug: false` и отсутствие искусственного секрета
в автоматическом ERROR log. Для JsonCast отдельно проверяйте повреждённую строку
и корректный JSON null. [Полный контракт полей](dto.md#обязательные-поля-и-ошибки-гидратации).

## Live-тестирование (real-request)

Для тестов, выполняющих реальные HTTP-запросы к API провайдера, см. отдельный гайд:
[Live-тестирование](./live-testing.md).

Что даёт core ApiSutra:
- `LiveEnvLoader` — загрузка `.env.live.local` без дополнительных зависимостей;
- `LivePolling::waitUntil()` — polling-хелпер для async/live flow;
- `LiveResultAssertions::assertSuccess()` и `assertDataInstanceOf()` — проверки technical success result-layer и ожидаемого типа `data()`.

Что остаётся на стороне SDK-провайдера:
- решение, нужны ли live-тесты вообще;
- env-конвенция конкретного SDK;
- `LiveEnv`, `LiveTestGuard`, `LiveClientFactory`, `LiveFixtureLoader`, `LiveResponseDumper`;
- структура `tests/Live`, Makefile/DX запуска, balance preflight, response dumping.

Практическое правило:
- если SDK небольшой, sandbox-ориентированный и покрывает основные риски через mock/unit-тесты, live-контур может быть минимальным;
- если SDK живёт поверх production-like API, включает async/file flows или дорогие запросы, live primitives и provider-side support-слой обычно стоит планировать сразу.

## Где детали
- `docs/glossary/testing.md`
- `docs/guides/transport.md`
- `docs/guides/live-testing.md`


В Laravel provider подключается package discovery без обязательной публикации конфига.
Обычный DI сохраняет заданные значения SDK-запроса; перенос входящих HTTP-данных
выполняется явной RequestFactory. Пользовательские bindings имеют приоритет.
[Подключение, миграция и тестирование](laravel.md).


## Ошибки recording и миграция

Recorder включается явно. Неудачная запись возвращает `execution_error` с
`reason=recording_failed` и `RecordingException`; в `response` сохраняется фактический
HTTP-ответ, а в `previous` — техническая причина. Автоматическое сообщение не содержит
пути каталога или тела. В result-first режиме проверяйте ошибку; при throwOnErrors
обрабатывайте исключение.

HTTP к этому моменту уже выполнен: например, POST создал объект, но записать fixture
не удалось. SDK не повторяет HTTP из-за этой ошибки даже при разрешённом POST retry
и `retryExceptions: [Throwable::class]`. Приложение также должно отличать её от
сетевой ошибки, чтобы не создать объект повторно.

Fixture публикуется целиком под свободным именем, без перезаписи существующего файла.
Конкурирующие recorder получают разные имена. Прерванный процесс может оставить
скрытый временный файл `.recording-*`, но не частично опубликованную JSON fixture.
Корректные большие JSON и текст не обрезаются лимитом safe debug/log. Невалидный UTF-8
вызывает явную ошибку записи вместо пустого файла; нового бинарного формата нет.
Потоки сохраняют bodyOmitted/size и не читаются ради fixture.

## Проверки самого пакета

Команды выполняются из исходного checkout ApiSutra. Для потребителя пакета эти
инструменты не нужны; production-установка `composer install --no-dev` их исключает.

```bash
composer install
composer validate --strict
composer dump-autoload --optimize --strict-psr
composer test -- --fail-on-deprecation --fail-on-warning
composer lint
composer analyse
composer check-docs
composer check-package
composer install --working-dir=tests/Integration/Laravel
php tests/Integration/Laravel/verify.php
```

`check-docs` и `check-package` требуют Python 3.9+. Первая команда проверяет локальные
пути Markdown-ссылок. Вторая сравнивает Git archive HEAD и Composer archive рабочей
копии, затем устанавливает каждый архив без dev-пакетов и запускает внешние smoke, включая PHP-пример быстрого старта из README.
Для ещё не закоммиченных изменений сначала добавьте предназначенные файлы в index
и вызовите `composer check-package -- --staged`. Отчёт можно сохранить через `--report`.

PHPStan проверяет весь src на уровне 5 с точным baseline существующих замечаний.
Новые ошибки и устаревшие записи baseline блокируют проверку. Это не утверждение
о полном отсутствии долга по типам. PSR-12 проверяется для src: ошибки блокируют
проверку, предупреждения о рекомендуемой длине строки остаются видимыми.

CI включает PHP 8.4/8.5, locked/lowest/latest зависимости, качество, архивы и
изолированное Laravel 12 приложение. Lowest определяется Composer с действующими
ограничениями совместимости и безопасности; это не установка заведомо уязвимых
исторических версий. Для воспроизведения в отдельном checkout:

```bash
composer update --prefer-lowest --prefer-stable --no-interaction
composer test
# Актуальный совместимый набор:
composer update --prefer-stable --no-interaction
composer test
```

Laravel-проверка использует fake HTTP и не требует БД/Redis. Она проверяет HTTP,
Artisan, последовательные задания в одном процессе и config:cache; отдельный
queue worker и Octane в эту проверку не входят. Само наличие CI-конфигурации
не заменяет успешный прогон на конкретном commit.

На проверенном lowest-наборе старые Guzzle/PSR-7, PSR HTTP Factory, Symfony
Translation и deep-copy выдают deprecation под PHP 8.4/8.5. Это зафиксированное
ограничение старых зависимостей: их диагностика остаётся видимой в отдельном CI job.
Locked/latest проходят с `--fail-on-deprecation`; для lowest сохраняется вывод
`--display-deprecations`, без глобального подавления E_DEPRECATED. Обновление
совместимых зависимостей устраняет эти предупреждения. Runtime constraints не
сужены только ради исключения старых предупреждений из отчёта.

## Redis rate-limit

Из корня исходного репозитория после `composer install`:

```bash
composer test:redis
```

Нужен работающий Docker с Compose (например, Docker Desktop; на Windows запускать
из WSL). Команда сама собирает PHP с phpredis, поднимает отдельный Redis, дожидается
healthcheck и запускает Redis-тесты. Локальный PHP не требует расширения redis.
При первом запуске загрузка образов и сборка могут занять несколько минут;
следующие запуски используют кеш сборки.

После успеха, ошибки или обычного прерывания Ctrl+C скрипт удаляет контейнеры,
тома и сеть своего запуска. Образ остаётся для повторного использования.
Каждый запуск получает отдельный Compose project; порты Redis не публикуются
на хост. Исходники подключены только для чтения. Код завершения тестов сохраняется;
ошибка очистки также завершает команду неуспешно.

Можно передавать параметры Pest:

```bash
composer test:redis -- --filter=NOSCRIPT
```

`composer test` сохраняет обычный запуск без Docker: Redis-сценарии пропускаются.
Локальный стенд использует PHP 8.4 / phpredis 6.2 / Redis 7.0; CI дополнительно
проверяет PHP 8.5 / phpredis 6.3 / Redis 8.2. В обязательном Redis job отсутствие
расширения или сервера вызывает ошибку, а не пропуск тестов.

Для ручного запуска при уже установленном phpredis и выделенном тестовом сервере:

```bash
APISUTRA_TEST_REDIS=1 APISUTRA_REDIS_HOST=127.0.0.1 APISUTRA_REDIS_PORT=6379 vendor/bin/pest tests/Integration/Redis
APISUTRA_TEST_REDIS=1 APISUTRA_REDIS_HOST=127.0.0.1 APISUTRA_REDIS_PORT=6379 php tests/Integration/Laravel/verify.php
```

**Не направляйте ручные проверки на Redis приложения:** тесты меняют ACL/maxmemory
и очищают script cache. Команда `composer test:redis` создаёт собственный сервер
и не использует адрес Redis из окружения приложения.

Стенд находится в `tests/Integration/Redis/compose.yaml`, запуск и очистка — в
`tests/Support/test-redis.sh`. Проверяются отдельные workers с барьером, общая квота,
TTL, NOSCRIPT, ACL/OOM и потеря ответа после исполнения команды. Узкий тестовый
прокси нужен только для проверки неизвестного исхода; в SDK он не поставляется.
