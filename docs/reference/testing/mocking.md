# Fake и проверки запросов

ApiSutra предоставляет базовые testing primitives: `fake`, фикстуры, assert‑методы
и несколько framework-agnostic helper-классов.

Важно:
- этот гайд описывает именно возможности core-пакета;
- архитектура live-suite конкретного SDK (support-слой, структура папок, Makefile, дампы, preflight и т.д.) строится на стороне SDK-провайдера;
- live-стратегия зависит от провайдера и не является обязательной частью каждого SDK.

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
