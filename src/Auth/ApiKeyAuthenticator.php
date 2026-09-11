<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Auth;

use Brahmic\ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Cache\CacheIdentityProviderInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Override;

final readonly class ApiKeyAuthenticator implements AuthenticatorInterface, CacheIdentityProviderInterface
{
    public function __construct(
        private string $key,
        private ?string $header = 'X-Api-Key',
        private ?string $query = null,
    ) {}

    #[Override]
    public function getCacheIdentity(?PreparedRequest $request = null): ?string
    {
        return CacheCredentialIdentity::forRequest(
            hash('sha256', serialize([self::class, $this->key, $this->header, $this->query])),
            $request,
            $this->header !== null ? [$this->header] : [],
            $this->header === null ? $this->query : null,
        );
    }

    #[Override]
    public function authenticate(PreparedRequest $request): PreparedRequest
    {
        if ($this->header !== null) {
            return $request->withHeader($this->header, $this->key);
        }

        if ($this->query !== null) {
            $separator = str_contains($request->url, '?') ? '&' : '?';
            $url = $request->url . $separator . rawurlencode($this->query) . '=' . rawurlencode($this->key);
            return $request->with(url: $url);
        }

        return $request;
    }

    #[Override]
    public function shouldRefresh(): bool
    {
        return false;
    }

    #[Override]
    public function getRefreshRequest(): ?RequestInterface
    {
        return null;
    }

    #[Override]
    public function processTokenResponse(ResponseDtoInterface $response): void
    {
    }
}
