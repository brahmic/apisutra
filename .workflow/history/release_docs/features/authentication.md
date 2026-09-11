# Аутентификация

## Обзор

SDK поддерживает различные схемы аутентификации через единый интерфейс `AuthenticatorInterface`.

## AuthenticatorInterface

```php
interface AuthenticatorInterface
{
    /**
     * Добавить аутентификацию к запросу
     */
    public function authenticate(PreparedRequest $request): PreparedRequest;
    
    /**
     * Нужно ли обновить credentials?
     */
    public function shouldRefresh(): bool;
    
    /**
     * Запрос для получения/обновления токена
     */
    public function getRefreshRequest(): ?RequestInterface;
    
    /**
     * Обработать ответ с новым токеном
     */
    public function processTokenResponse(ResponseDtoInterface $response): void;
}
```

## Простые схемы (из коробки)

### API Key

```php
$client = new Client(
    config: new ClientConfig(
        auth: new ApiKeyAuthenticator(
            key: 'your-api-key',
            header: 'X-Api-Key',  // в заголовке
        ),
    )
);

// Или в query параметре
new ApiKeyAuthenticator(
    key: 'your-api-key',
    query: 'api_key',  // ?api_key=your-api-key
);
```

### Basic Auth

```php
$client = new Client(
    config: new ClientConfig(
        auth: new BasicAuthenticator(
            username: 'user',
            password: 'pass',
        ),
    )
);
```

### Bearer Token (статичный)

```php
$client = new Client(
    config: new ClientConfig(
        auth: new BearerAuthenticator(token: 'your-token'),
    )
);
```

## Динамический токен

Для токенов с коротким сроком жизни, которые нужно обновлять.

### Реализация

```php
class TokenAuthenticator implements AuthenticatorInterface, CacheAwareInterface
{
    private ?CacheInterface $cache = null;
    private ?string $token = null;
    private ?int $expiresAt = null;
    
    public function __construct(
        private readonly string $username,
        private readonly string $password,
        private readonly int $tokenTtl = 600,
    ) {}
    
    public function setCache(CacheInterface $cache): void
    {
        $this->cache = $cache;
        $this->loadFromCache();  // Загрузить токен из кеша
    }

    public function getCacheKey(): string
    {
        return 'auth_token_' . $this->username;
    }
    
    public function authenticate(PreparedRequest $request): PreparedRequest
    {
        return $request->withHeader('Authorization', "Bearer {$this->token}");
    }
    
    public function shouldRefresh(): bool
    {
        // Нет токена или истекает через 30 сек
        return $this->token === null 
            || $this->expiresAt < time() + 30;
    }
    
    public function getRefreshRequest(): RequestInterface
    {
        return new GetTokenRequest(
            username: $this->username,
            password: $this->password,
        );
    }
    
    public function processTokenResponse(ResponseDtoInterface $response): void
    {
        $this->token = $response->accessToken;
        $this->expiresAt = time() + $this->tokenTtl;
        $this->saveToCache();
    }
    
    private function loadFromCache(): void
    {
        $data = $this->cache->get($this->getCacheKey());
        if ($data) {
            $this->token = $data['token'];
            $this->expiresAt = $data['expires_at'];
        }
    }
    
    private function saveToCache(): void
    {
        $this->cache->set($this->getCacheKey(), [
            'token' => $this->token,
            'expires_at' => $this->expiresAt,
        ], $this->tokenTtl);
    }
}
```

### Запрос токена (обычный Request)

```php
#[Post('/auth/token')]
#[Returns(TokenResponse::class)]
#[NoAuth]  // Важно! Без аутентификации
readonly class GetTokenRequest extends AbstractRequest
{
    public function __construct(
        #[Body] public string $username,
        #[Body] public string $password,
    ) {}
}

readonly class TokenResponse extends AbstractResponseDto
{
    public function __construct(
        public string $accessToken,
        public int $expiresIn,
    ) {}
}
```

### Поток выполнения

```
Пользователь: $client->orders()->get(123)->send()
                            ↓
SDK: authenticator.shouldRefresh()?
                            ↓
        ДА → authenticator.getRefreshRequest()
              возвращает GetTokenRequest
                            ↓
             SDK выполняет GetTokenRequest (без auth!)
                            ↓
             authenticator.processTokenResponse(TokenResponse)
             токен сохраняется в кеш
                            ↓
SDK: PreparedRequest для GetOrder
                            ↓
SDK: authenticator.authenticate(PreparedRequest)
     добавляет Authorization header
                            ↓
SDK: отправляет HTTP запрос
```

## OAuth2 с Refresh Token

```php
// Фрагмент — конструктор, setCache(), authenticate() аналогично TokenAuthenticator
class OAuth2Authenticator implements AuthenticatorInterface, CacheAwareInterface
{
    private ?string $accessToken = null;
    private ?string $refreshToken = null;
    private ?int $expiresAt = null;
    
    public function shouldRefresh(): bool
    {
        return $this->accessToken === null 
            || $this->expiresAt < time() + 30;
    }
    
    public function getRefreshRequest(): RequestInterface
    {
        if ($this->refreshToken) {
            // Обновление через refresh token
            return new RefreshTokenRequest(
                refreshToken: $this->refreshToken,
            );
        }
        
        // Первичная авторизация
        return new GetOAuthTokenRequest(
            clientId: $this->clientId,
            clientSecret: $this->clientSecret,
        );
    }
    
    public function processTokenResponse(ResponseDtoInterface $response): void
    {
        $this->accessToken = $response->accessToken;
        $this->refreshToken = $response->refreshToken;
        $this->expiresAt = time() + $response->expiresIn;
        $this->saveToCache();
    }
}
```

## Составные схемы

### Несколько заголовков

```php
class MultiHeaderAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $clientId,
    ) {}
    
    public function authenticate(PreparedRequest $request): PreparedRequest
    {
        return $request
            ->withHeader('X-Api-Key', $this->apiKey)
            ->withHeader('X-Client-Id', $this->clientId)
            ->withHeader('X-Timestamp', (string) time());
    }
    
    public function shouldRefresh(): bool
    {
        return false;  // Статичные credentials
    }
    
    public function getRefreshRequest(): ?RequestInterface
    {
        return null;
    }
    
    public function processTokenResponse(ResponseDtoInterface $response): void
    {
        // Не используется
    }
}
```

### HMAC подпись

```php
class HmacAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $secret,
    ) {}
    
    public function authenticate(PreparedRequest $request): PreparedRequest
    {
        $timestamp = time();
        $signature = $this->sign($request, $timestamp);
        
        return $request
            ->withHeader('X-Api-Key', $this->apiKey)
            ->withHeader('X-Timestamp', (string) $timestamp)
            ->withHeader('X-Signature', $signature);
    }
    
    private function sign(PreparedRequest $request, int $timestamp): string
    {
        $data = implode("\n", [
            $request->method,
            $request->url,
            $request->body ?? '',
            $timestamp,
        ]);
        
        return hash_hmac('sha256', $data, $this->secret);
    }
    
    public function shouldRefresh(): bool
    {
        return false;
    }
    
    public function getRefreshRequest(): ?RequestInterface
    {
        return null;
    }
    
    public function processTokenResponse(ResponseDtoInterface $response): void {}
}
```

## Публичные endpoints (без auth)

### Атрибут #[NoAuth]

```php
#[Get('/public/status')]
#[Returns(StatusResponse::class)]
#[NoAuth]  // SDK пропускает аутентификацию
readonly class GetPublicStatus extends AbstractRequest {}
```

### Рантайм

```php
$request->withoutAuth()->send();
```

## Retry при 401

Если API вернул 401 (токен протух раньше ожидаемого):

1. SDK выполняет `getRefreshRequest()` → `processTokenResponse()`
2. Повторяет основной запрос с новым токеном

```php
$client = new Client(
    config: new ClientConfig(
        auth: $authenticator,
        authRetryOn401: true,  // default: true
        authRetryAttempts: 1,  // сколько раз пытаться refresh
    )
);
```

Если refresh не удался после `authRetryAttempts`, SDK возвращает
`ExecutionResult` со статусом `FAILED` и ошибкой `ErrorCode::Unauthorized`.
При `throwOnErrors` или `result->throw()` — выбрасывается исключение.

**Приоритеты:**
- Для 401 сначала выполняется auth‑refresh (если `authRetryOn401 = true`).
- Общий retry не применяется к 401, чтобы избежать двойных повторов и дублей.

## Хранение токена

### CacheAwareInterface

Authenticator'ы, которым нужен кеш, реализуют интерфейс-маркер:

```php
interface CacheAwareInterface
{
    public function setCache(CacheInterface $cache): void;
    public function getCacheKey(): string;
}
```

SDK автоматически инжектит кеш из ClientConfig при инициализации клиента.

### Использование

```php
// Кеш указывается один раз — в ClientConfig
$client = new Client(
    config: new ClientConfig(
        cache: $psrCache,  // PSR-16
        auth: new TokenAuthenticator(
            username: 'user',
            password: 'pass',
            // cache не нужен — SDK инжектит автоматически
        ),
    )
);
```

### Реализация в authenticator

```php
class TokenAuthenticator implements AuthenticatorInterface, CacheAwareInterface
{
    private ?CacheInterface $cache = null;
    
    public function __construct(
        private readonly string $username,
        private readonly string $password,
    ) {}
    
    public function setCache(CacheInterface $cache): void
    {
        $this->cache = $cache;
    }

    public function getCacheKey(): string
    {
        return 'auth_token_' . $this->username;
    }
    
    // ... остальные методы используют $this->cache
}
```

Токен хранится в кеше и переживает перезапуск процесса.
