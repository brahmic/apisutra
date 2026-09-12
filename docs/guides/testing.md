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
