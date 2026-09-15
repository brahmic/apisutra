# Выбор аутентификации

Настройки авторизации в `ClientConfig`.

`ClientConfig::auth` задаёт стратегию по умолчанию, `authScopes` — именованные
стратегии, `authPolicy` — проверку применимости. По умолчанию auth/policy не заданы,
scopes пуст. Приоритеты и runtime-сброс описаны ниже.

## Базовый authenticator

Для `ApiKeyAuthenticator` с `header: null, query: 'api_key'` ключ добавляется
после query base URL, endpoint и полей. Повторная авторизация подготовленного запроса
заменяет только ранее добавленную SDK пару; исходные одноимённые параметры сохраняются.
Fragment до авторизации удаляется. Правила URI — в [сериализации](../serialization/uri-query.md#uri-и-path).

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
