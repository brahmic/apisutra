<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pipeline\Cache;

use Brahmic\ApiSutra\Config\CacheConfig;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Cache\CacheIdentityProviderInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;

/** Автоматические границы доступа отделены от пользовательского логического ключа. */
final readonly class CacheIdentityResolver
{
    public function __construct(private ClientConfig $config, private string $provider) {}

    public function scope(?AuthenticatorInterface $auth, CacheConfig $cache, string $prefix): ?string
    {
        $identity = $auth === null ? 'anonymous' : $this->provided($auth);
        $tenant = $cache->identity === null ? 'default' : $this->provided($cache->identity);
        if ($identity === null || $tenant === null) {
            return null;
        }

        return hash('sha256', serialize([
            'apisutra-scope-v3', $this->provider, $this->config->baseUrl, $identity, $tenant, $prefix,
        ]));
    }

    public function request(RequestInterface $request): ?string
    {
        return $request instanceof CacheIdentityProviderInterface ? $this->provided($request) : 'default';
    }

    private function provided(object $provider, ?PreparedRequest $request = null): ?string
    {
        if (!$provider instanceof CacheIdentityProviderInterface) {
            return null;
        }
        $identity = $provider->getCacheIdentity($request);
        if ($identity === null || trim($identity) === '') {
            return null;
        }

        return hash('sha256', serialize([$provider::class, $identity]));
    }

    /** Защитные признаки custom key; обычные HTTP-параметры не включаются. */
    public function customGuard(PreparedRequest $prepared, RequestInterface $request, ?AuthenticatorInterface $auth, CacheConfig $cache): ?string
    {
        $headers = $this->headers($prepared);
        $authIdentity = $auth === null ? 'anonymous' : $this->provided($auth, $prepared);
        $tenantIdentity = $request instanceof CacheIdentityProviderInterface ? $this->provided($request, $prepared) : 'default';
        $configuredIdentity = $cache->identity === null ? 'default' : $this->provided($cache->identity, $prepared);
        if ($authIdentity === null || $tenantIdentity === null || $configuredIdentity === null) {
            return null;
        }

        $credentials = array_intersect_key($headers, array_flip([
            'authorization', 'proxy-authorization', 'cookie', 'x-api-key', 'api-key', 'x-auth-token',
        ]));
        $url = parse_url($prepared->url);
        if ($url === false || !isset($url['scheme'], $url['host'])) {
            return null;
        }
        $queryCredentials = [];
        foreach (explode('&', $url['query'] ?? '') as $part) {
            $name = strtolower(urldecode(explode('=', $part, 2)[0]));
            if (in_array($name, ['access_token', 'api_key', 'apikey', 'token', 'key', 'password', 'client_secret', 'signature'], true)) {
                $queryCredentials[] = $part;
            }
        }

        return hash('sha256', serialize([
            $authIdentity, $tenantIdentity, $configuredIdentity,
            strtolower($url['scheme']), strtolower($url['host']), $url['port'] ?? null,
            $url['user'] ?? null, $url['pass'] ?? null, $credentials, $queryCredentials,
        ]));
    }

    /** @return array<string, list<string>> */
    private function headers(PreparedRequest $prepared): array
    {
        $headers = [];
        foreach ($prepared->headers as $name => $value) {
            $headers[strtolower($name)][] = $value;
        }
        ksort($headers);

        return $headers;
    }
}
