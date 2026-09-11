<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Auth;

use Brahmic\ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;

final readonly class HmacAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        private string $apiKey,
        private string $secret,
    ) {}

    #[\Override]
    public function authenticate(PreparedRequest $request): PreparedRequest
    {
        $timestamp = time();
        $signature = $this->sign($request, $timestamp);

        return $request
            ->withHeader('X-Api-Key', $this->apiKey)
            ->withHeader('X-Timestamp', (string) $timestamp)
            ->withHeader('X-Signature', $signature);
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

    private function sign(PreparedRequest $request, int $timestamp): string
    {
        $data = implode("\n", [
            $request->method->value,
            $request->url,
            $request->body ?? '',
            $timestamp,
        ]);

        return hash_hmac('sha256', $data, $this->secret);
    }
}
