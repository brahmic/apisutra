<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pipeline\Auth;

use Brahmic\ApiSutra\Auth\TokenAuthenticator;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Cache\CacheIdentityProviderInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use Brahmic\ApiSutra\Pipeline\Diagnostics\AuditLogger;
use Psr\Log\LogLevel;
use WeakMap;

/** Привязки живут вместе с pipeline; исходный встроенный authenticator не меняется. */
final class AuthBindingResolver
{
    /** @var WeakMap<AuthenticatorInterface, array<string, AuthBinding>> */
    private WeakMap $bindings;
    private readonly string $localIdentity;

    public function __construct(
        private readonly ClientConfig $config,
        private readonly string $provider,
        private readonly ClockInterface $clock,
    ) {
        $this->bindings = new WeakMap();
        $this->localIdentity = bin2hex(random_bytes(16));
    }

    public function resolve(AuthenticatorInterface $auth, ?string $authScope): AuthBinding
    {
        $identity = $auth instanceof CacheIdentityProviderInterface ? $auth->getCacheIdentity() : null;
        $tenant = $this->config->cacheConfig?->identity;
        $tenantIdentity = $tenant === null ? 'default' : $tenant->getCacheIdentity();
        $shared = $identity !== null && trim($identity) !== '' && $tenantIdentity !== null && trim($tenantIdentity) !== '';
        $scope = hash('sha256', serialize([
            'apisutra-auth-scope-v1', $this->provider, $this->config->baseUrl, $auth::class,
            $identity, $authScope, $tenant === null ? null : $tenant::class, $tenantIdentity,
            $shared ? null : [$this->localIdentity, spl_object_id($auth)],
        ]));
        $bindings = $this->bindings[$auth] ?? [];
        if (isset($bindings[$scope])) {
            return $bindings[$scope];
        }
        $store = $shared ? $this->config->cacheConfig?->store : null;
        if (!$shared) {
            (new AuditLogger($this->config))->log(LogLevel::DEBUG, 'Auth token cache использует локальную область', [
                'reason' => 'auth_cache_identity_unavailable',
            ]);
        }
        $binding = new AuthBinding(
            $auth instanceof TokenAuthenticator ? $auth->freshForContext($this->clock) : $auth,
            new AuthTokenCache($store, $scope, $this->clock),
            hash('sha256', serialize(['apisutra-auth-v1', 'refresh-lock', $scope])),
        );
        $bindings[$scope] = $binding;
        $this->bindings[$auth] = $bindings;
        return $binding;
    }
}
