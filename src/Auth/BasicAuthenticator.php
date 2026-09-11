<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Auth;

use Brahmic\ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;

final readonly class BasicAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        private string $username,
        private string $password,
    ) {}

    #[\Override]
    public function authenticate(PreparedRequest $request): PreparedRequest
    {
        $token = base64_encode($this->username . ':' . $this->password);
        return $request->withHeader('Authorization', 'Basic ' . $token);
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
