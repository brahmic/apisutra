<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Auth;

use Brahmic\ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;

final readonly class BearerAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        private string $token,
    ) {}

    #[\Override]
    public function authenticate(PreparedRequest $request): PreparedRequest
    {
        return $request->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    #[\Override]
    public function shouldRefresh(): bool
    {
        return false;
    }

    #[\Override]
    public function getRefreshRequest(): ?RequestInterface
    {
        return null;
    }

    #[\Override]
    public function processTokenResponse(ResponseDtoInterface $response): void
    {
    }
}
