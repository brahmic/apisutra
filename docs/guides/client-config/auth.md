# Auth

Настройки авторизации в `ClientConfig`.

## Базовая настройка
```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Auth\BearerAuthenticator;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    auth: new BearerAuthenticator('token'),
);
```

## Auth scopes
Когда нужно несколько вариантов авторизации (например, разные токены).
```php
$config = new ClientConfig(
    baseUrl: 'https://api.example',
    authScopes: [
        'system' => new BearerAuthenticator('system-token'),
        'user' => new BearerAuthenticator('user-token'),
    ],
);
```

Если задан только `auth` — он применяется ко всем запросам,
кроме помеченных `#[NoAuth]`.
Если вы используете только `authScopes`, то scope нужно указывать
на каждом запросе (`#[AuthScope]` или runtime‑override), иначе auth не применится.

Выбор scope на запросе:
```php
use Brahmic\ApiSutra\Attributes\Request\AuthScope;
use Brahmic\ApiSutra\Core\AbstractRequest;
use App\Provider\Auth\ProviderScope;

#[AuthScope(ProviderScope::System)] // рекомендуемый вариант
final class SystemRequest extends AbstractRequest {}

#[AuthScope('system')] // legacy-совместимый вариант
final class LegacySystemRequest extends AbstractRequest {}
```

## AuthPolicy
Ограничивает, какие запросы можно выполнять с дефолтным auth.

Без `AuthPolicy` дефолтный `auth` применяется ко всем запросам.
`AuthPolicy` полезен, когда нужно явно ограничить список запросов
для дефолтной авторизации.

## Refresh‑логика
Параметры:
- `authRetryOn401` — повтор при 401
- `authRetryAttempts` — количество попыток

## Граница: Auth vs Credentials Enrichment
`auth`/`authScopes` отвечают только за авторизацию (обычно `headers`/`query` через authenticator).
Если провайдер требует служебные креды в payload (`body`/`multipart form`), используйте
`credentialsConfig` (встроенный `CredentialsEnricher`), а не `AuthenticatorInterface`.

Типичный сценарий:
- `authScopes` выбирает способ авторизации
- `credentialsConfig.scopes` подмешивает provider‑поля в request parts
- scope обычно совпадает (`#[AuthScope(ProviderScope::System)]`), но при необходимости
  можно переопределить отдельно через `withCredentialsScope(...)`

```php
use Brahmic\ApiSutra\Auth\BearerAuthenticator;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\CredentialsEnrichmentConfig;
use Brahmic\ApiSutra\Config\CredentialsScopeConfig;
use Brahmic\ApiSutra\Enums\Request\CredentialsMergeMode;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    authScopes: [
        'system' => new BearerAuthenticator('system-token'),
    ],
    credentialsConfig: new CredentialsEnrichmentConfig(
        mergeMode: CredentialsMergeMode::FillMissing,
        scopes: [
            'system' => new CredentialsScopeConfig(
                body: ['payload.auth.client_id' => 'client-id'],
                query: ['api_key' => 'api-key'],
            ),
        ],
        secretKeys: ['api_key', 'client_secret'],
    ),
);
```

## Где детали
- Атрибуты: `docs/guides/attributes/request.md`
- Поведение auth: `docs/technical/pipeline.md`
- Примеры для всех встроенных authenticator: `docs/guides/auth.md`
- Сериализация и этап enrichment: `docs/guides/serialization.md`
- Debug и redaction: `docs/guides/client-config/observability.md`
