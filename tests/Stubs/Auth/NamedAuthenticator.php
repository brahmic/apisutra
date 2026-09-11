<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Auth;

use Brahmic\ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;

final class NamedAuthenticator implements AuthenticatorInterface
{
    /**
     * @var array<string, int>
     */
    public static array $calls = [];

    public function __construct(
        private readonly string $name,
    ) {}

    public static function reset(): void
    {
        self::$calls = [];
    }

    public function authenticate(PreparedRequest $request): PreparedRequest
    {
        self::$calls[$this->name] = (self::$calls[$this->name] ?? 0) + 1;

        return $request->withHeader('X-Auth-' . $this->name, 'token');
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
