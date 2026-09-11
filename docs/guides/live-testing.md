# Live-тестирование (real-request)

Гайд по организации тестов, выполняющих реальные HTTP-запросы к API провайдера.

Важно:
- это не жёсткий контракт core ApiSutra, а набор рекомендуемых provider-side паттернов;
- разные SDK могут использовать только часть этого гайда;
- если у провайдера есть полноценный sandbox и дешёвые smoke-сценарии, live-suite может быть минимальным или вовсе отсутствовать.

## Уровни применимости

Чтобы не путать “опционально” с “редко нужно”, полезно делить live-паттерны на 4 уровня:
- базовый минимум — техническая основа live-проверок;
- рекомендуется по умолчанию — для большинства production SDK это быстро окупается;
- нужен при определённых условиях — включается по признакам API;
- операторское удобство — повышает DX и сопровождаемость, но не задаёт контракт SDK.

Практика показывает: если SDK не учебный, а живёт поверх реального внешнего API, то
многие паттерны из этого гайда используются не эпизодически, а регулярно. Поэтому ниже
важно читать “опционально” как “не часть core-контракта”, а не как “почти никогда не нужно”.

## Что даёт core, а что строит SDK

### Core ApiSutra

Core-пакет предоставляет только базовые primitives:
- `LiveEnvLoader` — загрузка `.env.live.local` в окружение;
- `LivePolling::waitUntil()` — polling-хелпер;
- `LiveResultAssertions::assertSuccess()` и `assertDataInstanceOf()` — framework-agnostic assertions для technical success result-layer и ожидаемого типа `data()`.

`assertSuccess()` не выполняет бизнес-оценку ответа. Он проверяет только, что
`ResolvedResultInterface` помечен как success на уровне result-layer. Бизнес-валидность
ответа SDK-разработчик оценивает отдельно: branded result, свои predicates, анализ DTO,
meta и provider-specific статусов.

### Provider-side слой

Всё остальное из этого гайда относится к архитектуре конкретного SDK:
- `LiveEnv`, `LiveTestGuard`, `LiveClientFactory`, `LiveFixtureLoader`, `LiveResponseDumper`;
- структура `tests/Live/*`;
- группировка live-suite;
- кеширование, response dumping, preflight, баланс, Makefile и операторский DX.

Ни один из этих элементов не является обязательным только потому, что SDK построен на ApiSutra.
Но на практике для production SDK с десятками endpoint-ов, сочетанием sync/async flow,
файлами, нестабильными ответами или дорогими запросами такой support-слой обычно стоит
считать рекомендуемым по умолчанию, а не экзотикой.

## Когда нужны live-тесты

Unit-тесты с `MockTransport` проверяют логику SDK: сериализацию, гидрацию, маппинг ошибок.
Но есть задачи, которые они не покрывают:
- реальный контракт провайдера (поля, статусы, коды ошибок);
- поведение при rate-limit, таймаутах, нестабильной сети;
- корректность async/polling flow с реальными задержками;
- бинарные ответы (файлы, архивы).

Live-тесты особенно важны, когда:
- у провайдера нет sandbox или тестового стенда;
- запросы платные и нужен контроль расхода;
- ответы зависят от реальных данных внешней системы.

Live-тесты обычно не нужны или могут быть сильно упрощены, если:
- у провайдера есть стабильный sandbox с предсказуемыми тестовыми данными;
- все рискованные сценарии уже хорошо покрываются mock/unit-тестами;
- SDK не работает с файлами, async-flow или дорогими endpoint-ами.

## Принципы

- Сильно рекомендуется: ручной запуск. Для production-like, платных или нестабильных endpoint-ов live-тесты обычно не запускаются в CI автоматически.
- Сильно рекомендуется: env-gating. Без явного флага и credentials тесты лучше пропускать (`skip`), а не падать.
- Рекомендуется: минимальный конфиг. Держите обязательные настройки live-suite небольшими.
- Опционально: кеширование. Полезно для дорогих или повторяемых сценариев, но не обязательно для каждого SDK.
- Опционально: response dumping. Нужен, если ответы дорогие, большие, file-based или требуют анализа вне теста.
- Опционально: группировка. Имеет смысл для большого live-suite; для нескольких smoke-тестов может быть избыточной.

## Когда включать какой функционал

### Базовый минимум

- `LiveEnvLoader` нужен почти всегда, если в SDK вообще есть live-тесты.
- `LiveResultAssertions` почти всегда стоит использовать как технический baseline перед business-checks.
- `LiveTestGuard` или эквивалентная skip-логика практически обязательны, если live-запуск зависит от env и credentials.

### Рекомендуется по умолчанию

- `record/playback` обычно стоит включать, если API платный, медленный, rate-limited или заметно недетерминированный.
- provider-side `LiveClientFactory` обычно окупается, если live-клиент требует отдельные timeout/cache/debug настройки.
- группировка live-suite почти всегда полезна, когда в SDK больше нескольких live-сценариев.
- встроенные helper-методы ApiSutra особенно полезны, когда live-тесты должны быть единообразными между несколькими пакетами.

### Нужен при определённых условиях

- `LivePolling::waitUntil()` нужен, если у провайдера есть async-job, status endpoint, eventual consistency или отложенная готовность результата.
- response dumping сильно рекомендуется, если API асинхронный, file-based, плохо документирован или требует пост-анализа ответа вне теста.
- live-cache нужен, если запросы дорогие, медленные, лимитированные или часто повторяются при отладке.
- negative live-suite нужен, если ошибки составляют существенную часть DX SDK и для них есть дешёвые и безопасные сценарии.
- preflight-проверки стоимости/баланса нужны, если API платный или имеет чувствительные лимиты.
- Laravel smoke имеет смысл, если пакет поставляет service provider, container bindings, config integration или facade/DI слой.

### Операторское удобство

- `LiveFixtureLoader` полезен, если живые сценарии требуют наборов входных данных и их приходится переиспользовать между тестами.
- `LiveResponseDumper` полезен, если команда расследует нестабильные ответы, сохраняет бинарные файлы или использует дампы как временный источник состояния.
- Makefile или эквивалентный task-runner удобен, если suite запускается разными группами и его используют не только автор пакета.

## Структура

Один из возможных provider-side вариантов:

```text
tests/
├── Unit/
├── Integration/
└── Live/
    ├── Sync/
    ├── Async/
    ├── Negative/
    ├── Laravel/
    ├── Fixtures/
    │   ├── live.dataset.example.php
    │   └── live.dataset.php
    ├── Support/
    │   ├── LiveEnv.php
    │   ├── LiveTestGuard.php
    │   ├── LiveClientFactory.php
    │   ├── LiveFixtureLoader.php
    │   └── LiveResponseDumper.php
    └── .cache/
```

Это только пример. Для части SDK хватит пары файлов, а отдельный `Support/` может быть не нужен.

## Support-слой

Рекомендуемый provider-side набор классов:

| Класс | Назначение |
|-------|------------|
| `LiveEnv` | Чтение env через `getenv()`. Типизированные методы: `apiKey()`, `baseUrl()`, `isEnabled()`. Не знает о файлах. |
| `LiveTestGuard` | Skip-логика: пропустить тест, если флаг запуска или credentials не установлены. |
| `LiveClientFactory` | Создание клиента с `HttpTransport`. Кеш, timeouts и прочие live-настройки добавляются только если это оправдано для провайдера. |
| `LiveFixtureLoader` | Загрузка входных данных из `live.dataset.php`. |
| `LivePolling` | **ApiSutra.** `Brahmic\ApiSutra\Testing\LivePolling::waitUntil(callback, isReady, timeout)` для async/polling flow. Вызовы с `->withoutCache()`. |
| `LiveResultAssertions` | **ApiSutra.** `assertSuccess()` и `assertDataInstanceOf()`. Проверка technical success result-layer и DTO-контракта. |
| `LiveResponseDumper` | Опциональный helper для сохранения ответов в файлы. |

## Env-конвенция

### Файлы

Обычно в корне SDK-пакета:
- `.env.live.example` — шаблон без секретов;
- `.env.live.local` — реальные значения, в `.gitignore`.

### Обязательные переменные

Стремитесь к минимуму:

```env
PROVIDER_LIVE_TESTS=1
PROVIDER_API_KEY=your-key-here
```

Остальное задавайте только если это действительно нужно конкретному SDK.

### Загрузка

ApiSutra предоставляет `LiveEnvLoader`:

```php
// tests/bootstrap.php
require_once __DIR__ . '/../vendor/autoload.php';

\Brahmic\ApiSutra\Testing\LiveEnvLoader::loadForTests(__DIR__);
```

`loadForTests(__DIR__)` ищет `.env.live.local` в корне пакета. Файл опционален.
Существующие переменные окружения не перезаписываются.

### Provider-side reader

Один из возможных вариантов:

```php
final class LiveEnv
{
    public static function isEnabled(): bool
    {
        $value = getenv('PROVIDER_LIVE_TESTS');
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function apiKey(): string
    {
        return (string) getenv('PROVIDER_API_KEY');
    }

    public static function baseUrl(): string
    {
        $url = getenv('PROVIDER_LIVE_BASE_URL');
        return is_string($url) && $url !== '' ? $url : 'https://api.provider.com/v1';
    }
}
```

## ApiSutra helper-методы

### `LivePolling::waitUntil()`

Polling до готовности:

```php
use Brahmic\ApiSutra\Testing\LivePolling;

$status = LivePolling::waitUntil(
    fetch: fn () => $client->resource()->checkStatus($id)->withoutCache()->send()->resolved()->data(),
    isReady: fn ($dto) => $dto->status === Status::Ready,
    timeoutSeconds: 30,
    intervalMilliseconds: 1000,
);
```

`->withoutCache()` особенно важен, если статус меняется во времени.

### `LiveResultAssertions`

```php
use Brahmic\ApiSutra\Testing\LiveResultAssertions;

$resolved = $client->resource()->get($id)->send()->resolved();
LiveResultAssertions::assertSuccess($resolved, 'resource/get');
LiveResultAssertions::assertDataInstanceOf($resolved, UserDto::class, 'resource/get');
$user = $resolved->data();
```

Эти helper-методы не заменяют provider-specific assertions:
- если провайдер возвращает business status внутри payload, проверяйте его отдельно;
- если нужен branded result, business-инварианты удобно инкапсулировать именно там.

## Разделение данных: env vs фикстуры

Чёткое разграничение:

| Что | Где | Пример |
|-----|-----|--------|
| Credentials (API-ключи) | `.env.live.local` | `PROVIDER_API_KEY=abc123` |
| Конфигурация (base URL, флаги) | `.env.live.local` | `PROVIDER_LIVE_TESTS=1` |
| Входные данные для запросов | `live.dataset.php` | Идентификаторы, адреса, даты |

Фикстуры входных данных в PHP-файле удобны, но это provider-side pattern, а не требование core ApiSutra.

```php
// tests/Live/Fixtures/live.dataset.example.php
return [
    'entity_id' => 'EXAMPLE-ID-123',
    'search_query' => 'Пример запроса',
    'date' => '2025-01-15',
];
```

## Кеширование

Кеширование полезно, если live-запросы дорогие, медленные или повторяются.
Для маленького и дешёвого smoke-suite отдельный live-cache может быть не нужен.

ClientConfig принимает `Psr\SimpleCache\CacheInterface` (PSR-16). Для файлового кеша
можно использовать Symfony Cache (`FilesystemAdapter` + `Psr16Cache`) или любую другую
PSR-16-совместимую реализацию.

```php
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

$cacheDir = __DIR__ . '/../.cache';
$cache = new Psr16Cache(new FilesystemAdapter('', 0, $cacheDir));

$client = ProviderClient::make(
    apiKey: LiveEnv::apiKey(),
    transport: $transport,
    cache: $cache,
    cacheTtl: 604800,
);
```

Файловый кеш часто становится хорошим provider-side default для production SDK, но остаётся
архитектурным решением самого SDK, а не контрактом ApiSutra.

## Response dumping

Response dumping нужен не всегда. Это provider-side pattern для случаев, когда:
- ответы дорогие и их нужно анализировать без повторного вызова;
- есть file/download сценарии;
- в async-flow полезно сохранять промежуточные идентификаторы и состояния.

На практике для SDK с sync + async сценариями, бинарными ответами или слабой внешней
документацией response dumping часто оказывается не просто удобством, а рабочим инструментом
диагностики и воспроизводимости.

Один из возможных подходов:
- JSON-ответы сохранять как `{resource}/{method}.json`;
- файловые ответы сохранять как бинарник + `.meta.json`;
- при необходимости использовать дампы как источник состояния для следующих прогонов.

## Группировка тестов

Группировка live-тестов имеет смысл, если suite достаточно большой. Для пары smoke-тестов
можно обойтись без отдельных групп.

Один из вариантов:
- `live-check` — preflight;
- `live-sync` — синхронные методы;
- `live-async` — async/polling flow;
- `live-negative` — проверка error-handling;
- `live-laravel` — container smoke.

## Negative-тесты

Это опциональный слой. Добавляйте его, если:
- у провайдера есть устойчивые и безопасные negative-сценарии;
- ошибки составляют важную часть DX SDK;
- стоимость таких проверок контролируема.

Рекомендации:
- используйте только дешёвые методы;
- выбирайте безопасные сценарии: невалидный формат идентификатора, пустые обязательные поля;
- не используйте валидный формат с несуществующими данными, если провайдер может списать за попытку;
- проверяйте тип ошибки, error code, контекст и корректность маппинга.

## Контроль стоимости

Этот раздел актуален только для платных или лимитированных API. Для бесплатного sandbox
он обычно не нужен.

Возможные provider-side меры:
- preflight-проверка баланса;
- кеш для снижения повторных списаний;
- вывод дельты баланса до/после прогона;
- ручной запуск только осознанных сценариев.

## Makefile DX

Makefile-обвязка полностью опциональна. Она удобна для большого live-suite, но не входит
в контракт ApiSutra.

Один из возможных вариантов:

```makefile
PEST = ./vendor/bin/pest
LIVE_ENV = .env.live.local

live-sync:
	@set -a; . $(LIVE_ENV); set +a; $(PEST) --group live-sync

live-one:
	@set -a; . $(LIVE_ENV); set +a; $(PEST) --filter "$(TEST)"
```

## .gitignore

Обычно имеет смысл добавить:

```gitignore
.env.live.local
tests/Live/.cache/
tests/Live/Fixtures/live.dataset.php
.live-dumps/
```

## Где детали

- Основное тестирование (fake, mock, фикстуры): [Тестирование](./testing.md)
- Транспорт и MockTransport: [Transport](./transport.md)
- Кеширование и per-request управление: [Cache](./client-config/cache.md)
- Async/polling: [Provider Async Await](./provider-async-await.md), [Continuation Token](./continuation-token.md)
