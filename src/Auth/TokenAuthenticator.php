<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Auth;

use Brahmic\ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Cache\CacheAwareInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Psr\SimpleCache\CacheInterface;

final class TokenAuthenticator implements AuthenticatorInterface, CacheAwareInterface
{
    private ?CacheInterface $cache = null;
    private ?string $token = null;
    private ?int $expiresAt = null;

    public function __construct(
        private readonly string $username,
        private readonly string $password,
        private readonly int $tokenTtl = 600,
        private readonly ?string $refreshRequestClass = null,
    ) {}

    #[\Override]
    public function setCache(CacheInterface $cache): void
    {
        $this->cache = $cache;
        $this->loadFromCache();
    }

    #[\Override]
    public function getCacheKey(): string
    {
        return 'auth_token_' . $this->username;
    }

    #[\Override]
    public function authenticate(PreparedRequest $request): PreparedRequest
    {
        if ($this->token === null) {
            return $request;
        }

        return $request->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    #[\Override]
    public function shouldRefresh(): bool
    {
        return $this->token === null || ($this->expiresAt !== null && $this->expiresAt < time() + 30);
    }

    #[\Override]
    public function getRefreshRequest(): ?RequestInterface
    {
        if ($this->refreshRequestClass === null) {
            return null;
        }

        return new $this->refreshRequestClass(
            username: $this->username,
            password: $this->password,
        );
    }

    #[\Override]
    public function processTokenResponse(ResponseDtoInterface $response): void
    {
        if (property_exists($response, 'accessToken')) {
            $this->token = $response->accessToken;
        }

        if (property_exists($response, 'expiresIn')) {
            $this->expiresAt = time() + (int) $response->expiresIn;
        } else {
            $this->expiresAt = time() + $this->tokenTtl;
        }

        $this->saveToCache();
    }

    private function loadFromCache(): void
    {
        if ($this->cache === null) {
            return;
        }

        $data = $this->cache->get($this->getCacheKey());
        if (is_array($data)) {
            $this->token = $data['token'] ?? null;
            $this->expiresAt = $data['expires_at'] ?? null;
        }
    }

    private function saveToCache(): void
    {
        if ($this->cache === null) {
            return;
        }

        $this->cache->set($this->getCacheKey(), [
            'token' => $this->token,
            'expires_at' => $this->expiresAt,
        ], $this->tokenTtl);
    }
}
