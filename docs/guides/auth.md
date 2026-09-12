# Аутентификация

Короткий гайд по авторизации запросов, scope и политике доступа.

Для готового URL и чужого origin автоматическое наследование auth выключено.
Явный выбор credentials ограничен [OriginPolicy](client-config/auth.md#originpolicy);
`forceAuth()` не отменяет эту проверку. Подробности: [внешние URL](external-urls.md).

## Базовый authenticator

Для `ApiKeyAuthenticator` с `header: null, query: 'api_key'` ключ добавляется
после query base URL, endpoint и полей. Повторная авторизация подготовленного запроса
заменяет только ранее добавленную SDK пару; исходные одноимённые параметры сохраняются.
Fragment до авторизации удаляется. Правила URI — в [сериализации](serialization.md#uri-и-path).

```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;

final class ExampleAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        private string $token,
    ) {}

    public function authenticate(PreparedRequest $request): PreparedRequest
    {
        return $request->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    public function shouldRefresh(): bool
    {
        return false;
    }

    public function getRefreshRequest(): ?RequestInterface
    {
        return null;
    }

    public function processTokenResponse(ResponseDtoInterface $response): void
    {
    }
}

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    auth: new ExampleAuthenticator('token'),
);
```

## API key в query
Если токен должен передаваться в query‑параметре, используйте встроенный
`ApiKeyAuthenticator` и укажите `query` (а `header` отключите):

```php
use Brahmic\ApiSutra\Auth\ApiKeyAuthenticator;

$config = $config->with(
    auth: new ApiKeyAuthenticator('token', header: null, query: 'api_key'),
);
```

## API key в header
По умолчанию `ApiKeyAuthenticator` кладёт ключ в `X-Api-Key`:
```php
use Brahmic\ApiSutra\Auth\ApiKeyAuthenticator;

$config = $config->with(
    auth: new ApiKeyAuthenticator('token'),
);
```

## Bearer token
```php
use Brahmic\ApiSutra\Auth\BearerAuthenticator;

$config = $config->with(
    auth: new BearerAuthenticator('token'),
);
```

## Basic Auth
```php
use Brahmic\ApiSutra\Auth\BasicAuthenticator;

$config = $config->with(
    auth: new BasicAuthenticator('user', 'pass'),
);
```

## HMAC‑подпись
```php
use Brahmic\ApiSutra\Auth\HmacAuthenticator;

$config = $config->with(
    auth: new HmacAuthenticator('api-key', 'secret'),
);
```

## AuthorizationSchemeAuthenticator
Подходит для схем вида `Authorization: Scheme key="value", ts="..."`.

```php
use Brahmic\ApiSutra\Auth\Authorization\QueryLikeFormatter;
use Brahmic\ApiSutra\Auth\AuthorizationSchemeAuthenticator;
use Brahmic\ApiSutra\Contracts\Interfaces\Auth\AuthorizationParamsProviderInterface;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;

final readonly class SignatureParamsProvider implements AuthorizationParamsProviderInterface
{
    public function __construct(
        private string $apiKey,
        private string $secret,
    ) {}

    public function resolve(PreparedRequest $request): array
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', $request->url . $timestamp, $this->secret);

        return [
            'key' => $this->apiKey,
            'ts' => $timestamp,
            'sign' => $signature,
        ];
    }
}

$config = $config->with(
    auth: new AuthorizationSchemeAuthenticator(
        scheme: 'Signature',
        provider: new SignatureParamsProvider('api-key', 'secret'),
    ),
);
```

Если нужен формат `key=value&key2=value2` без кавычек, используйте `QueryLikeFormatter`:
```php
$config = $config->with(
    auth: new AuthorizationSchemeAuthenticator(
        scheme: 'ReestroAuth',
        params: [
            'apiKey' => 'key',
            'portal.orgid' => 'org',
        ],
        formatter: new QueryLikeFormatter(),
    ),
);
```

## TokenAuthenticator + refresh
```php
use Brahmic\ApiSutra\Auth\TokenAuthenticator;
use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\DataTransfer\AbstractResponseDto;

#[Post('/auth/token')]
#[Returns(AuthTokenResponseDto::class)]
final class AuthTokenRequest extends AbstractRequest
{
    public function __construct(
        #[Body]
        public string $username,
        #[Body]
        public string $password,
    ) {}
}

final readonly class AuthTokenResponseDto extends AbstractResponseDto
{
    public function __construct(
        public string $accessToken,
        public int $expiresIn,
    ) {}
}

$config = $config->with(
    auth: new TokenAuthenticator(
        username: 'user',
        password: 'pass',
        tokenTtl: 600,
        refreshRequestClass: AuthTokenRequest::class,
    ),
);
```

Если нужен кеш токена, передайте PSR‑16 store в `ClientConfig::cache`
или `ClientConfig::cacheConfig->store`.

## Authorization: scheme + параметры
Если нужен нестандартный формат `Authorization` (схема + параметры),
в большинстве случаев достаточно `AuthorizationSchemeAuthenticator`.
Для особых случаев можно реализовать `AuthenticatorInterface` вручную:
```php
final class CustomSchemeAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        private string $key,
        private string $secret,
    ) {}

    public function authenticate(PreparedRequest $request): PreparedRequest
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', $this->key . $timestamp, $this->secret);
        $value = sprintf(
            'Custom key="%s", ts="%s", sign="%s"',
            $this->key,
            $timestamp,
            $signature,
        );

        return $request->withHeader('Authorization', $value);
    }

    public function shouldRefresh(): bool
    {
        return false;
    }

    public function getRefreshRequest(): ?RequestInterface
    {
        return null;
    }

    public function processTokenResponse(ResponseDtoInterface $response): void
    {
    }
}
```

## Несколько scope
```php
use Brahmic\ApiSutra\Attributes\Request\AuthScope;
use Brahmic\ApiSutra\Core\AbstractRequest;
use App\Provider\Auth\ProviderScope;

$config = $config->with(authScopes: [
    'public' => new ApiKeyAuthenticator('public'),
    'private' => new ApiKeyAuthenticator('private'),
]);

#[AuthScope(ProviderScope::Private)] // рекомендуемый вариант
final class GetPrivateData extends AbstractRequest {}

#[AuthScope('private')] // legacy-совместимый вариант
final class LegacyPrivateData extends AbstractRequest {}
```

По умолчанию, если задан только `auth`, он применяется ко всем запросам
(кроме `#[NoAuth]`/`withoutAuth()`).
Если вы используете только `authScopes` и не задали `auth`, то scope нужно
выбирать явно на запросе (`#[AuthScope]`) или через runtime‑override,
иначе авторизация не будет применена.

## Управление на уровне запроса
- `#[AuthScope]` — выбрать scope.
- `#[NoAuth]` — отключить auth на запросе.
- Runtime: `withAuth()`, `withoutAuth()`, `withAuthScope()`, `forceAuth()`, `forceAuthScope()`.

```php
use Brahmic\ApiSutra\Attributes\Behavior\NoAuth;

#[NoAuth]
final class PublicPing extends AbstractRequest {}

$request = (new PublicPing())->withoutAuth();
$request = (new PublicPing())->forceAuth(); // использовать только для исключений
```

`withAuthScope()` не пробивает `#[NoAuth]`; предыдущий runtime `withoutAuth()`
заменяется новым runtime-выбором.
`forceAuthScope()` пробивает запрет и нужен только для редких исключений.

## Приоритеты выбора auth
Порядок принятия решения:
1) `forceAuth/forceAuthScope`
2) `#[NoAuth]` / `withoutAuth()`
3) runtime‑override scope (`withAuthScope`)
4) `#[AuthScope]`
5) `withAuth()`
6) `AuthPolicyInterface`
7) дефолтный `auth` из `ClientConfig`

## Сброс runtime scope

`withAuth()`, `withoutAuth()` и `forceAuth()` очищают предыдущий runtime scope.
Например, `withAuthScope('secondary')->withoutAuth()->withAuth()` возвращает
выбор к default credentials, если у класса нет `#[AuthScope]`. При наличии
атрибута после сброса снова действует его scope. Исходная execution-копия
с `secondary` не меняется.

`withOptions()` заменяет полный снимок опций: очищенный scope execution не
восстанавливается из runtime scope исходного request. Правила `NoAuth`,
`AuthPolicy` и разрешение через `forceAuth` сохраняются. В цепочке runtime-методов
последняя настройка заменяет предыдущую; например, `forceAuth()->withoutAuth()`
отключает авторизацию.

**Изменение совместимости:** раньше переданный для сброса null сохранял предыдущий
scope. Для сохранения выбранного scope используйте явный `withAuthScope()` или
`forceAuthScope()`. Необязательные TTL и connect timeout этим исправлением не меняются.

## Политика доступа
```php
use Brahmic\ApiSutra\Contracts\Interfaces\Auth\AuthPolicyInterface;

final class OnlyWritePolicy implements AuthPolicyInterface
{
    public function allowedRequests(): array
    {
        return [
            CreateUser::class,
            UpdateUser::class,
        ];
    }
}

$config = $config->with(authPolicy: new OnlyWritePolicy());
```

Без `AuthPolicy` дефолтный `auth` применяется ко всем запросам.
`AuthPolicy` нужен, если хотите ограничить auth только частью запросов
или разделить системные/пользовательские вызовы.

## Встроенные аутентификаторы
- `BearerAuthenticator` — `Authorization: Bearer <token>`
- `ApiKeyAuthenticator` — API‑ключ в header или query
- `BasicAuthenticator` — `Authorization: Basic base64(user:pass)`
- `AuthorizationSchemeAuthenticator` — `Authorization: Scheme key="value", ...`
- `TokenAuthenticator` — токен с refresh‑запросом и кешированием
- `HmacAuthenticator` — подпись запроса (X‑Api‑Key/X‑Timestamp/X‑Signature)

## Refresh и 401
- `AuthenticatorInterface::shouldRefresh()` и `getRefreshRequest()` управляют обновлением токена.
- `authRetryOn401` отключает retry по 401 (по умолчанию включено).
- `authRetryAttempts` задаёт число попыток refresh.

### Изоляция токенов без дополнительных настроек

Обычный `ClientConfig(baseUrl: ..., auth: new TokenAuthenticator(...))` не требует
новых параметров. Без cache store токен сохраняется локально внутри клиента.
С PSR-16 store SDK автоматически разделяет токены по классу клиента, настроенному
base URL (включая base path), identity authenticator, выбранному auth scope и
объявленной `CacheConfig::identity`, если она есть. Identity встроенного
TokenAuthenticator учитывает username, password и refreshRequestClass.

Никаких обязательных prefix, account ID или lock key нет. SDK не вводит сущности
аккаунтов внешнего API: он разделяет уже переданные конфигурации авторизации.
Адрес конкретного ресурса и текущее значение access token не меняют область refresh.
Origin policy продолжает отдельно определять, куда разрешено передать credentials.

Физические token/lock keys — отдельные версионированные digest длиной 64 символа,
без открытых username, credentials или URL. Одинаковый объявленный контекст получает
одинаковые ключи в разных процессах. Digest не является шифрованием секретов.

Встроенный TokenAuthenticator получает отдельное состояние token/expiresAt для
каждой привязки к клиенту и scope. Повторное использование исходного auth-объекта
в другом клиенте не переносит его токен. Прямой `setCache()` при смене store очищает
старую локальную привязку; cache miss в прежнем store сохраняет действующий локальный
токен. Методы `freshForContext()`, `loadFromCache()` и `tokenVersion()` используются
SDK для привязки и перечитывания; вызывать их в обычном клиентском коде не требуется.

### Refresh lock

По умолчанию SDK использует локальную службу блокировок без настройки backend.
Она действует внутри данного клиента/pipeline, не между независимыми клиентами
или процессами. PSR-16 cache остаётся пригодным для хранения токенов, но сам по себе
не гарантирует атомарных блокировок. Одного дополнительного метода `add()` недостаточно.

Выбор службы происходит автоматически в таком порядке:

1. Необязательный `CacheConfig::locks`, если явно задан.
2. Выбранный cache store, если он реализует `AuthLockProviderInterface`.
3. Локальная служба SDK.

Для собственных backend контракт находится в
`Brahmic\ApiSutra\Contracts\Interfaces\Auth`: `AuthLockProviderInterface::acquire()`
возвращает `AuthLockLeaseInterface` либо null, если блокировка занята. Provider обязан
атомарно захватывать отсутствующую/истёкшую запись. `lease->release()` атомарно удаляет
только запись своего владельца; при потере владения возвращает false. Последовательность
`get → compare → delete` без атомарности этому контракту не соответствует.

Отдельный backend подключают, когда нужна координация между процессами:

```php
use Brahmic\ApiSutra\Config\CacheConfig;

// $lockProvider — реализация AuthLockProviderInterface выбранного хранилища.
$cacheConfig = new CacheConfig(store: $tokenStore, locks: $lockProvider);
```

Это необязательное расширение. SDK не подключает Redis или Laravel автоматически
и не обещает адаптер для каждого Laravel lock driver. Ошибка явно выбранного backend
не включает тихий fallback к локальной блокировке.

TTL и максимальное ожидание берутся из `ClientConfig::timeout` с границами 5–30 секунд.
Локальный срок измеряется монотонными часами, ожидание использует SleeperInterface
и общий deadline. Блокировка защищает только в пределах своего TTL: продление lease,
fencing и гарантия единственного refresh после истечения TTL не реализованы.

Встроенный TokenAuthenticator перечитывает store при ожидании и после захвата lease.
Действующий токен другого владельца позволяет пропустить лишний refresh. После 401
прежний отвергнутый токен не считается обновлением: требуется другое значение.
Если lock не получен и пригодный токен не появился, результат — `timeout` с причиной
`auth_refresh_lock_timeout`, stage `auth_lock_wait`; основной HTTP не отправляется.
Истечение общего бюджета сохраняет `execution_deadline_exceeded` и исходный 401,
если он уже был. Общий HTTP retry не повторяет ошибки auth-блокировки.

Исключение backend даёт `execution_error` с причиной `auth_lock_backend_error`.
Если release упал после успешного refresh, основной HTTP не отправляется. При
существующем отказе refresh/deadline ошибка release не подменяет первичную причину;
в logger передаётся только безопасное описание вторичной ошибки.

## Кеш для токенов

CacheAwareInterface получает изолированный PSR-16 view, а не исходный общий store.
`getCacheKey()` задаёт логический ключ; view преобразует его в физическую область,
включая bulk-операции. `clear()` отклоняется, чтобы не очистить чужие данные;
используйте `delete()`/`deleteMultiple()` для своих логических ключей.

Для общего token store пользовательский authenticator объявляет стабильную identity
через CacheIdentityProviderInterface. При отсутствии identity либо неопределённой
identity подключения SDK даёт локальное хранилище этой auth внутри клиента. При
DEBUG-логировании доступна причина `auth_cache_identity_unavailable`. Настраивать
фиктивный prefix вместо identity не нужно.

Произвольное mutable состояние собственного authenticator остаётся ответственностью
его автора. SDK не клонирует такие объекты неявно и не предполагает, что любой
`setCache()` перечитывает токен. Автоматическое разделение памяти и оптимизация
повторного refresh после ожидания обеспечены для встроенного TokenAuthenticator.

### Миграция token cache

Старые `auth_token_<username>` и `auth_refresh_lock:...` не читаются как fallback и
не удаляются глобально. После обновления возможен однократный refresh: безопасно
определить владельца старой записи только по username нельзя. Предзаполненные
старые token records и токен, вручную помещённый в исходный unbound TokenAuthenticator,
не переносятся в новые pipeline-привязки.

Если custom auth ранее получала исходный cache store, учтите scoped view и локальный
режим без identity. Если backend выбирался благодаря одному `add()`, для общей
блокировки потребуется реализация атомарного контракта. Настройка обычного клиента
с встроенным auth остаётся прежней. Прямое применение TokenAuthenticator без клиента
разделяет credentials, но не может автоматически учитывать неизвестный ему base URL.

## Где детали
- `docs/guides/attributes/request.md` — `AuthScope`
- `docs/guides/attributes/behavior.md` — `NoAuth`
- `docs/guides/client-config/auth.md`

## Identity для HTTP-кеша

Встроенные authenticators автоматически предоставляют identity: `prefix` для
изоляции учётных записей не требуется. Собственный authenticator может реализовать
`CacheIdentityProviderInterface`; без него HTTP-кеш пропускается. Метод получения
identity не выполняет авторизацию или refresh. Отдельный tenant провайдера объявляется
дополнительно. Контракт и ограничения описаны в [настройках кеша](client-config/cache.md).

## Бюджет авторизации и refresh

Если задан `RetryConfig::totalTimeoutMs`, начальная авторизация, ожидание refresh lock
и refresh после 401 используют deadline родителя. Дочерний запрос не получает новый
полный бюджет. При его исчерпании сохраняется `ExecutionDeadlineException`, повтор
refresh не начинается. Подробности — [Timeouts & Delay](client-config/timeouts-delay.md).
