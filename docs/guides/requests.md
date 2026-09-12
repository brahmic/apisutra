# Запросы

Краткий гайд по созданию запросов, опциям выполнения и отправке.

## Базовая структура
```php
use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/users')]
#[Returns(UserDto::class, unwrap: 'data')]
final class GetUser extends AbstractRequest
{
    public function __construct(
        #[Query('id')] public int $id,
    ) {}
}
```

## Источники данных
- `#[Query]` — параметры URL  
- `#[Body]` — тело запроса  
- `#[Path]` — плейсхолдеры в пути  
- `#[Header]` — заголовки  
- `#[File]` — файлы  

Полный перечень: `docs/guides/attributes/request.md`

## Body DTO для повторяющихся payload
Если одно и то же тело используется в нескольких запросах — вынесите его в DTO
и используйте как свойство запроса.

```php
use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Http\Put;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;

final readonly class UserPayload extends AbstractDto
{
    public function __construct(
        public string $email,
        public string $name,
    ) {}
}

#[Post('/users')]
final class CreateUser extends AbstractRequest
{
    public function __construct(
        #[Body('user')]
        public UserPayload $payload,
    ) {}
}

#[Put('/users/{id}')]
final class UpdateUser extends AbstractRequest
{
    public function __construct(
        public int $id,
        #[Body('user')]
        public UserPayload $payload,
    ) {}
}
```

DTO сериализуется через `#[To]`, касты и naming strategy. `#[Body]` задаёт
вложенный путь, если он нужен.

## Много полей в body без бойлерплейта
Если в request-классе много payload-полей, используйте class-level `RequestDefaults`
и оставляйте property-атрибуты только там, где нужен override:

```php
use Brahmic\ApiSutra\Attributes\Request\RequestDefaults;
use Brahmic\ApiSutra\Enums\Request\RequestUnmappedTarget;

#[RequestDefaults(unmapped: RequestUnmappedTarget::Body)]
final class PatchSomethingRequest extends AbstractRequest {}
```

Это рекомендуемый подход для «шумных» `POST/PUT/PATCH` запросов.
Полный контракт и приоритеты: [Request attributes](./attributes/request.md#requestdefaults).

## Корневой body для JSON Patch / bulk
Если endpoint ожидает body в виде `[...]` (а не `{...}`), используйте `BodyRoot`:

```php
use Brahmic\ApiSutra\Attributes\Request\BodyRoot;

#[BodyRoot]
public array $operations;
```

В этом случае SDK отправит `operations` как корневой payload.
Подробности и ограничения: [Request attributes](./attributes/request.md#bodyroot).

## OneOf и discriminator для polymorphic body
Для полиморфных payload используйте class-level `RequestOneOf` и `RequestDiscriminator`.
Так контракт проверяется до транспорта, а диагностика приходит в стандартном формате ошибки.

```php
use Brahmic\ApiSutra\Attributes\Request\RequestDiscriminator;
use Brahmic\ApiSutra\Attributes\Request\RequestOneOf;
use Brahmic\ApiSutra\Enums\Request\OneOfMode;

#[RequestOneOf(
    name: 'signature_payload',
    variants: [
        'cloudcrypt' => ['certificateId'],
        'goskey' => ['goskeyData'],
    ],
    requiredCommon: ['type', 'contents'],
    mode: OneOfMode::ExactlyOne,
)]
#[RequestDiscriminator(
    field: 'type',
    map: [
        'CERT_PROVIDER' => 'cloudcrypt',
        'HSM_PROVIDER' => 'goskey',
    ],
)]
final class CreateSignatureRequest extends AbstractRequest {}
```

Для вложенных контрактов можно использовать dot-path:
```php
#[RequestOneOf(
    name: 'signature_payload',
    variants: [
        'cloudcrypt' => ['signature.certificateId'],
        'goskey' => ['signature.goskey.data'],
    ],
    requiredCommon: ['type', 'signature.contents'],
    mode: OneOfMode::AtLeastOne,
)]
```

Подробности: [Request attributes: RequestOneOf](./attributes/request.md#requestoneof),
[Request attributes: RequestDiscriminator](./attributes/request.md#requestdiscriminator).

## Сериализация
Правила default‑маппинга и приоритеты описаны отдельно:
`docs/guides/serialization.md`

## Поведение запроса
Для cache/retry/timeout/rate‑limit используйте behavior‑атрибуты.  
Полный перечень: `docs/guides/attributes/behavior.md`

## Кеширование
Для управления кешем используйте:
- `withCache()` / `withoutCache()`
- `withCacheScope(string $scope)` — дополнительная метка кеша для исполнения внутри автоматической identity/tenant
- `clearCache()` — инвалидирование вариантов запроса в его пространстве

Подробнее: `docs/guides/client-config/cache.md`

## Авторизация
- `#[AuthScope]` — выбрать scope для запроса.
- `#[NoAuth]` — отключить auth.
- Runtime‑override: `withAuth()`, `withoutAuth()`, `withAuthScope()`, `forceAuthScope()`.

Обычно достаточно `#[AuthScope]` или дефолтного `auth` из `ClientConfig`.
`forceAuthScope()` используйте только если нужно пробить `#[NoAuth]`.

## Provider credentials enrichment
Если провайдер требует служебные креды в `body/query/multipart form`,
настраивайте это централизованно через `ClientConfig::credentialsConfig`,
а не дублируйте поля в каждом request-классе.

По умолчанию merge‑режим `fill-missing`:
- явные поля запроса не перезаписываются
- defaults провайдера заполняют только отсутствующие ключи

Для точечных исключений:
- `#[SkipCredentialsEnrichment]` — отключить enrichment для конкретного request
- runtime‑override:
  - `withCredentialsEnrichment()` / `withoutCredentialsEnrichment()`
  - `withCredentialsMergeMode(...)`
  - `withCredentialsScope(...)`

```php
use Brahmic\ApiSutra\Attributes\Request\SkipCredentialsEnrichment;
use Brahmic\ApiSutra\Enums\Request\CredentialsMergeMode;

$result = $request
    ->withCredentialsScope('system')
    ->withCredentialsMergeMode(CredentialsMergeMode::Overwrite)
    ->send();
```

## Пагинация
Для запросов с поддержкой пагинации:
```php
$result = $request->paginate()->all();
```
Режимы задаются через `PaginationRule` (single/all/pages/range) и могут быть переопределены:
```php
use Brahmic\ApiSutra\Pagination\PaginationRule;

$result = $request->rules(PaginationRule::pages(2))->send()->raw();
```
Подробнее: `docs/guides/attributes/behavior.md` и `docs/glossary/pagination.md`

## Runtime‑опции (частые)
```php
$result = $request
    ->withCache(60)
    ->withTimeout(5)
    ->withHeader('X-Trace-Id', $traceId)
    ->send();
```
Ещё примеры: `withRetry()`, `withoutCache()`, `withRateLimit()`, `withDelay()`, `withTraceId()`.

`withRetry(attempts)` сохраняет остальные параметры повторов и не подтверждает
безопасность POST/PATCH. Она определяется конфигом клиента и необязательным
`#[Retry(safe: true/false)]`; неуказанный safe и null равнозначны. Подробнее:
[безопасность повторов](retries-rate-limit.md#безопасность-повторов-без-обязательной-настройки).
Для credentials enrichment см. блок выше.

## Отправка и результат
```php
$handle = $request->send();          // sync
$handle = $request->sendAsync();     // async

$raw = $handle->raw();               // ExecutionResult
$data = $handle->dataOrFail();       // данные или исключение
$promise = $handle->rawAsync();      // PromiseInterface<ExecutionResult>
$resolved = $handle->resolvedAsync();// PromiseInterface<ResolvedResultInterface>
```

`sendAsync()` возвращает **ResultHandle**, а не `Promise`.
Для промиса используйте `rawAsync()` / `resolvedAsync()`.

Нюанс: при пагинации `sendAsync()` всё равно выполняет запросы синхронно,
а async‑обёртка возвращает уже готовый результат.
Исключения в async‑режиме проявляются при `wait()`/`throw()` или при `throwOnErrors`.

## Где детали
- Атрибуты запросов: `docs/guides/attributes/request.md`
- Атрибуты поведения: `docs/guides/attributes/behavior.md`
- Результаты: `docs/glossary/results.md`
- Валидация: `docs/guides/validation.md`
- Client discovery: `docs/guides/client-discovery.md`
- Resources: `docs/guides/resources.md`

## Полный URL для отдельного исполнения

`withUrl($url)` задаёт готовый абсолютный адрес, `withoutUrl()` очищает override.
Исходный request/config не меняется. Base path/query не дописываются; auth, общие
request enrichers и кеш автоматически не включаются. Для относительных endpoint
остаётся `withBaseUrl()`, со встроенной изоляцией credentials при смене origin.

Полный контракт, пример загрузки и миграция: [внешние URL](external-urls.md).

## Назначение скачиваемого файла

Для `#[Download]` доступны `withDownloadTo(string|StreamInterface $target, bool $overwrite = false)`
и `withoutDownloadTo()`. По умолчанию существующий путь защищён от замены; пользовательский
поток SDK не закрывает. Без цели используется временный файл автоматически.
Контракт сохранения и владения — [файлы](files.md).


`withCache(10)->withoutCache()->withCache()` вновь включает кеш с TTL 10:
null здесь означает «не задавать новый TTL». `withoutRateLimit()` выключает применение
лимитера, даже если объект прежнего override сохранён внутри опций. Свежая execution-копия
наследует исходные request-опции; для полного нового набора применяйте `RequestOptions::empty()`
через явный `RequestExecution`, а не предположение о неявном сбросе всех настроек.
