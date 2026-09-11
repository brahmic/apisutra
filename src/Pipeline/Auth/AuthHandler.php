<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pipeline\Auth;

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Auth\AuthPolicyInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Cache\CacheAwareInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Pipeline\PipelineExecutorInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Auth\AuthOverride;
use Brahmic\ApiSutra\Enums\Execution\RequestRole;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Exceptions\Request\UnauthorizedException;
use Brahmic\ApiSutra\Timing\SystemSleeper;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use Psr\SimpleCache\CacheInterface;

/**
 * Обработчик авторизации запроса.
 *
 * Нюансы:
 * - forceAuth/forceAuthScope пробивает #[NoAuth].
 * - порядок: forceAuth/forceAuthScope → #[NoAuth]/withoutAuth → runtime scope override → AuthScope → withAuth → AuthPolicy → default auth.
 */
final readonly class AuthHandler
{
    private const int REFRESH_LOCK_MIN_TTL_SECONDS = 5;
    private const int REFRESH_LOCK_MAX_TTL_SECONDS = 30;
    private const int REFRESH_LOCK_WAIT_STEP_MS = 50;

    private AuthRefreshLock $refreshLock;
    private SleeperInterface $sleeper;

    public function __construct(
        private ClientConfig $config,
        private PipelineExecutorInterface $executor,
        ?SleeperInterface $sleeper = null,
    ) {
        $this->refreshLock = new AuthRefreshLock($this->resolveCacheStore());
        $this->sleeper = $sleeper ?? new SystemSleeper();
    }

    public function handleAuthentication(RequestInterface $request, PipelineContext $context, bool $forceRefresh = false): void
    {
        if (!$request instanceof AbstractRequest) {
            return;
        }

        if ($this->shouldSkipAuth($request, $context)) {
            return;
        }

        $auth = $this->resolveAuthenticator($request, $context);
        if ($auth === null) {
            return;
        }

        $this->injectCache($auth);

        $shouldRefresh = $forceRefresh || $auth->shouldRefresh();
        $refreshAttempts = max(0, $this->config->authRetryAttempts);
        if ($forceRefresh) {
            $refreshAttempts = min(1, $refreshAttempts);
        }

        if ($shouldRefresh && $refreshAttempts > 0) {
            $this->refreshToken($auth, $context, $request, $refreshAttempts, $forceRefresh);
        }

        if ($context->preparedRequest !== null) {
            $context->preparedRequest = $auth->authenticate($context->preparedRequest);
        }
    }

    /** Выбор auth без refresh, внедрения store и вызова authenticate. */
    public function resolveForCache(RequestInterface $request, PipelineContext $context): ?AuthenticatorInterface
    {
        if (!$request instanceof AbstractRequest || $this->shouldSkipAuth($request, $context)) {
            return null;
        }

        return $this->resolveAuthenticator($request, $context);
    }

    private function shouldSkipAuth(AbstractRequest $request, PipelineContext $context): bool
    {
        $override = $this->resolveAuthOverride($request, $context);
        if ($override === AuthOverride::ForceEnable) {
            return false;
        }

        if ($request->hasNoAuth()) {
            return true;
        }

        if ($override === AuthOverride::Disable) {
            return true;
        }

        return false;
    }

    private function resolveAuthenticator(AbstractRequest $request, PipelineContext $context): ?AuthenticatorInterface
    {
        $scopeOverride = $this->resolveAuthScopeOverride($request, $context);
        if ($scopeOverride !== null) {
            return $this->resolveAuthScope($scopeOverride);
        }

        $scope = $request->getAuthScope();
        if ($scope !== null) {
            return $this->resolveAuthScope($scope);
        }

        $authOverride = $this->resolveAuthOverride($request, $context);
        if ($authOverride === AuthOverride::Enable || $authOverride === AuthOverride::ForceEnable) {
            if ($this->config->auth === null) {
                throw new ConfigurationException('Auth включен, но auth не настроен');
            }

            return $this->config->auth;
        }

        $policy = $this->config->authPolicy;
        if ($policy !== null && !$this->isAllowedByPolicy($policy, $request)) {
            return null;
        }

        if ($policy !== null && $this->config->auth === null) {
            throw new ConfigurationException('AuthPolicy задан, но auth не настроен');
        }

        return $this->config->auth;
    }

    private function resolveAuthScopeOverride(AbstractRequest $request, PipelineContext $context): ?string
    {
        if ($context->options !== null) {
            // Execution содержит полный снимок опций, включая явный сброс scope.
            return $context->options->getAuthScopeOverride();
        }

        return $request->getAuthScopeOverride();
    }

    private function resolveAuthOverride(AbstractRequest $request, PipelineContext $context): ?AuthOverride
    {
        $contextOverride = $context->options?->getAuthOverride();
        if ($contextOverride !== null) {
            return $contextOverride;
        }

        return $request->getAuthOverride();
    }

    private function resolveAuthScope(string $scope): AuthenticatorInterface
    {
        $auth = $this->config->authScopes[$scope] ?? null;
        if ($auth === null) {
            throw new ConfigurationException("Auth scope '{$scope}' не найден в конфиге");
        }

        return $auth;
    }

    private function isAllowedByPolicy(AuthPolicyInterface $policy, RequestInterface $request): bool
    {
        foreach ($policy->allowedRequests() as $class) {
            if ($request instanceof $class) {
                return true;
            }
        }

        return false;
    }

    private function refreshToken(AuthenticatorInterface $auth, PipelineContext $context, AbstractRequest $request, int $attempts, bool $forceRefresh): void
    {
        $lockKey = $this->resolveRefreshLockKey($auth);
        $lockTtlSeconds = $this->resolveRefreshLockTtlSeconds();
        $lockToken = $this->refreshLock->acquire($lockKey, $lockTtlSeconds);

        if ($lockToken === null) {
            $lockToken = $this->waitForRefreshLock($auth, $lockKey, $lockTtlSeconds, $forceRefresh);
            if ($lockToken === null) {
                return;
            }
        }

        try {
            for ($i = 0; $i < $attempts; $i++) {
                $refreshRequest = $auth->getRefreshRequest();
                if ($refreshRequest === null) {
                    return;
                }

                if ($refreshRequest instanceof AbstractRequest) {
                    $refreshRequest->setClient($request->getClient());
                    $refreshRequest = $refreshRequest->withoutAuth();
                }

                $result = $this->executor->execute($refreshRequest, RequestRole::Dependency, $context, $context->traceId);
                if ($result->isFailed()) {
                    continue;
                }

                if ($result->data instanceof ResponseDtoInterface) {
                    $auth->processTokenResponse($result->data);
                }

                return;
            }
        } finally {
            if ($lockToken !== null) {
                $this->refreshLock->release($lockKey, $lockToken);
            }
        }

        $prepared = $context->preparedRequest ?? new PreparedRequest(
            method: $request->getMethod(),
            url: $request->getEndpoint(),
        );

        throw new UnauthorizedException(
            'Не удалось обновить токен',
            new ProviderResponse(
                status: 401,
                headers: [],
                body: 'Unauthorized',
                request: $prepared,
                duration: 0,
            ),
        );
    }

    private function injectCache(AuthenticatorInterface $auth): void
    {
        if (!$auth instanceof CacheAwareInterface) {
            return;
        }

        $cache = $this->resolveCacheStore();
        if ($cache !== null) {
            $auth->setCache($cache);
        }
    }

    private function resolveCacheStore(): ?CacheInterface
    {
        return $this->config->cache ?? $this->config->cacheConfig?->store;
    }

    private function resolveRefreshLockKey(AuthenticatorInterface $auth): string
    {
        if ($auth instanceof CacheAwareInterface) {
            return 'auth_refresh_lock:' . $auth->getCacheKey();
        }

        return 'auth_refresh_lock:' . $auth::class . ':' . (string) spl_object_id($auth);
    }

    private function resolveRefreshLockTtlSeconds(): int
    {
        $ttl = $this->config->timeout;
        if ($ttl < self::REFRESH_LOCK_MIN_TTL_SECONDS) {
            return self::REFRESH_LOCK_MIN_TTL_SECONDS;
        }

        if ($ttl > self::REFRESH_LOCK_MAX_TTL_SECONDS) {
            return self::REFRESH_LOCK_MAX_TTL_SECONDS;
        }

        return $ttl;
    }

    private function waitForRefreshLock(AuthenticatorInterface $auth, string $lockKey, int $ttlSeconds, bool $forceRefresh): ?string
    {
        $maxWaitMs = $ttlSeconds * 1000;
        $waitedMs = 0;

        while ($waitedMs < $maxWaitMs) {
            if (!$forceRefresh && !$auth->shouldRefresh()) {
                return null;
            }

            $this->sleeper->sleepMs(self::REFRESH_LOCK_WAIT_STEP_MS);
            $waitedMs += self::REFRESH_LOCK_WAIT_STEP_MS;

            $lockToken = $this->refreshLock->acquire($lockKey, $ttlSeconds);
            if ($lockToken !== null) {
                return $lockToken;
            }
        }

        return $this->refreshLock->acquire($lockKey, $ttlSeconds);
    }
}
