<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Auth;

use Brahmic\ApiSutra\Auth\Authorization\QuotedCommaFormatter;
use Brahmic\ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Auth\AuthorizationParamsFormatterInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Auth\AuthorizationParamsProviderInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Stringable;

final readonly class AuthorizationSchemeAuthenticator implements AuthenticatorInterface
{
    /**
     * Параметры схемы Authorization.
     *
     * @param array<string, string|int|float|bool|Stringable|null> $params
     */
    public function __construct(
        private string $scheme,
        private ?string $token = null,
        private array $params = [],
        private ?AuthorizationParamsProviderInterface $provider = null,
        private ?AuthorizationParamsFormatterInterface $formatter = null,
    ) {}

    #[\Override]
    public function authenticate(PreparedRequest $request): PreparedRequest
    {
        $header = $this->buildHeaderValue($request);
        if ($header === null) {
            return $request;
        }

        return $request->withHeader('Authorization', $header);
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

    private function buildHeaderValue(PreparedRequest $request): ?string
    {
        if ($this->token !== null) {
            return $this->scheme . ' ' . $this->token;
        }

        $params = $this->resolveParams($request);
        if ($params === []) {
            return null;
        }

        return $this->scheme . ' ' . $this->formatParams($params);
    }

    /**
     * @return array<string, string|int|float|bool|Stringable>
     */
    private function resolveParams(PreparedRequest $request): array
    {
        $params = $this->params;
        if ($this->provider !== null) {
            $params = array_merge($params, $this->provider->resolve($request));
        }

        $filtered = [];
        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }
            $filtered[(string) $key] = $value;
        }

        return $filtered;
    }

    /**
     * @param array<string, scalar|Stringable> $params
     */
    private function formatParams(array $params): string
    {
        $formatter = $this->formatter ?? new QuotedCommaFormatter();
        return $formatter->format($params);
    }
}
