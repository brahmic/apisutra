<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Auth;

use Brahmic\ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Cache\CacheAwareInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Cache\CacheIdentityProviderInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Psr\SimpleCache\CacheInterface;

final class TokenCacheProbeAuthenticator implements AuthenticatorInterface, CacheAwareInterface, CacheIdentityProviderInterface
{
    public ?CacheInterface $cache = null;

    public function __construct(private ?string $identity)
    {
    }

    public function getCacheIdentity(?PreparedRequest $request = null): ?string
    {
        return $this->identity;
    }

    public function setCache(CacheInterface $cache): void
    {
        $this->cache = $cache;
    }

    public function getCacheKey(): string
    {
        return 'fixture:logical-token';
    }

    public function authenticate(PreparedRequest $request): PreparedRequest
    {
        return $request->withHeader('Authorization', 'Bearer ' . ($this->cache?->get($this->getCacheKey()) ?? 'empty'));
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
