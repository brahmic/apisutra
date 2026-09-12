<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pipeline\Auth;

use Brahmic\ApiSutra\Auth\TokenAuthenticator;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Auth\AuthLockLeaseInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Auth\AuthPolicyInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Cache\CacheAwareInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Pipeline\PipelineExecutorInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Auth\AuthOverride;
use Brahmic\ApiSutra\Enums\Execution\RequestRole;
use Brahmic\ApiSutra\Exceptions\Auth\AuthLockBackendException;
use Brahmic\ApiSutra\Exceptions\Auth\AuthRefreshLockTimeoutException;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Exceptions\Request\UnauthorizedException;
use Brahmic\ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use Brahmic\ApiSutra\Http\DestinationGuard;
use Brahmic\ApiSutra\Pipeline\Diagnostics\AuditLogger;
use Brahmic\ApiSutra\Timing\ExecutionBudget;
use Brahmic\ApiSutra\Timing\SystemClock;
use Brahmic\ApiSutra\Timing\SystemSleeper;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use Psr\Log\LogLevel;
use Psr\SimpleCache\CacheInterface;
use Throwable;

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
    private AuthBindingResolver $bindings;
    private ClockInterface $clock;
    private SleeperInterface $sleeper;

    public function __construct(
        private ClientConfig $config,
        private PipelineExecutorInterface $executor,
        ?SleeperInterface $sleeper = null,
        ?ClockInterface $clock = null,
        string $provider = self::class,
    ) {
        $this->clock = $clock ?? new SystemClock();
        $this->bindings = new AuthBindingResolver($config, $provider, $this->clock);
        $this->refreshLock = new AuthRefreshLock($this->resolveCacheStore(), $config->cacheConfig?->locks, $this->clock);
        $this->sleeper = $sleeper ?? new SystemSleeper();
    }

    public function handleAuthentication(RequestInterface $request, PipelineContext $context, bool $forceRefresh = false): void
    {
        DestinationGuard::checkContext($context);
        $context->budget?->check('authentication');
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

        $binding = $this->bindings->resolve($auth, $this->resolveAuthScopeOverride($request, $context) ?? $request->getAuthScope());
        $auth = $binding->auth;
        $previousVersion = $auth instanceof TokenAuthenticator
            ? ($this->sentTokenVersion($context) ?? $auth->tokenVersion()) : null;
        if ($auth instanceof CacheAwareInterface) {
            $auth->setCache($binding->cache);
        }

        $shouldRefresh = $forceRefresh || $auth->shouldRefresh();
        $refreshAttempts = max(0, $this->config->authRetryAttempts);
        if ($forceRefresh) {
            $refreshAttempts = min(1, $refreshAttempts);
        }

        $context->budget?->check('authentication');
        if ($shouldRefresh && $refreshAttempts > 0) {
            $this->refreshToken($auth, $context, $request, $refreshAttempts, $forceRefresh, $binding->lockKey, $previousVersion);
        }

        $context->budget?->check('authentication');
        if ($context->preparedRequest !== null) {
            $context->preparedRequest = $auth->authenticate($context->preparedRequest);
            DestinationGuard::checkContext($context);
            $context->budget?->check('authentication');
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
        if ($context->destination?->requiresIsolation()) {
            $explicit = in_array($override, [AuthOverride::Enable, AuthOverride::ForceEnable], true);
            if (!$explicit) {
                return true;
            }
            $context->destination->assertCredentialsAllowed($this->config->originPolicy);
        }
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

    private function refreshToken(
        AuthenticatorInterface $auth,
        PipelineContext $context,
        AbstractRequest $request,
        int $attempts,
        bool $forceRefresh,
        string $lockKey,
        ?string $previousVersion,
    ): void {
        $lease = $this->waitForRefreshLock($auth, $lockKey, $forceRefresh, $previousVersion, $context);
        if ($lease === null) {
            return;
        }
        $failure = null;
        try {
            $context->budget?->check('auth_lock_wait');
            // Другой владелец мог обновить токен между проверкой и захватом lease.
            if ($this->hasUpdatedToken($auth, $forceRefresh, $previousVersion)) {
                return;
            }
            for ($i = 0; $i < $attempts; $i++) {
                $context->budget?->check('auth_refresh');
                $refreshRequest = $auth->getRefreshRequest();
                $context->budget?->check('auth_refresh');
                if ($refreshRequest === null) {
                    return;
                }
                if ($refreshRequest instanceof AbstractRequest) {
                    $refreshRequest->setClient($request->getClient());
                    $refreshRequest = $refreshRequest->withoutAuth();
                }
                $result = $this->executor->execute($refreshRequest, RequestRole::Dependency, $context, $context->traceId);
                if ($result->exception instanceof ExecutionDeadlineException) {
                    throw $result->exception;
                }
                $context->budget?->check('auth_refresh', $result->exception);
                if ($result->isFailed()) {
                    continue;
                }
                if ($result->data instanceof ResponseDtoInterface) {
                    $auth->processTokenResponse($result->data);
                }
                return;
            }
            $prepared = $context->preparedRequest ?? new PreparedRequest($request->getMethod(), $request->getEndpoint());
            throw new UnauthorizedException(
                'Не удалось обновить токен',
                new ProviderResponse(401, [], 'Unauthorized', $prepared, 0),
            );
        } catch (Throwable $exception) {
            $failure = $exception;
            throw $exception;
        } finally {
            try {
                $lease->release();
            } catch (Throwable $exception) {
                if ($failure === null) {
                    $context->budget?->check('auth_lock_release', $exception);
                    throw new AuthLockBackendException($exception);
                }
                // Вторичная ошибка backend/logger не подменяет исходную причину отказа.
                try {
                    (new AuditLogger($this->config))->log(LogLevel::ERROR, 'Не удалось освободить auth-блокировку', [
                        'reason' => 'auth_lock_backend_error',
                    ]);
                } catch (Throwable) {
                }
            }
        }
    }

    private function resolveCacheStore(): ?CacheInterface
    {
        return $this->config->cache ?? $this->config->cacheConfig?->store;
    }

    private function resolveRefreshLockTtlSeconds(): int
    {
        return max(self::REFRESH_LOCK_MIN_TTL_SECONDS, min(self::REFRESH_LOCK_MAX_TTL_SECONDS, $this->config->timeout));
    }

    private function waitForRefreshLock(
        AuthenticatorInterface $auth,
        string $lockKey,
        bool $forceRefresh,
        ?string $previousVersion,
        PipelineContext $context,
    ): ?AuthLockLeaseInterface {
        $ttl = $this->resolveRefreshLockTtlSeconds();
        $maxWaitMs = $ttl * 1000;
        $waitedMs = 0;
        $start = $this->clock->monotonicMs();
        $budget = $context->budget ?? new ExecutionBudget($this->clock);
        while (true) {
            $budget->check('auth_lock_wait');
            if ($this->hasUpdatedToken($auth, $forceRefresh, $previousVersion)) {
                $budget->check('auth_lock_wait');
                return null;
            }
            $budget->check('auth_lock_wait');
            try {
                $lease = $this->refreshLock->acquireLease($lockKey, $ttl);
            } catch (Throwable $exception) {
                $budget->check('auth_lock_wait', $exception);
                throw new AuthLockBackendException($exception);
            }
            if ($lease !== null) {
                return $lease;
            }
            $budget->check('auth_lock_wait');
            $waitedMs = max($waitedMs, $this->clock->monotonicMs() - $start);
            if ($waitedMs >= $maxWaitMs) {
                throw new AuthRefreshLockTimeoutException();
            }
            $delay = min(self::REFRESH_LOCK_WAIT_STEP_MS, $maxWaitMs - $waitedMs);
            $budget->wait($delay, $this->sleeper, 'auth_lock_wait');
            $waitedMs += $delay;
        }
    }

    private function hasUpdatedToken(AuthenticatorInterface $auth, bool $forceRefresh, ?string $previousVersion): bool
    {
        if ($auth instanceof TokenAuthenticator) {
            $auth->loadFromCache();
            return !$auth->shouldRefresh() && (!$forceRefresh || $auth->tokenVersion() !== $previousVersion);
        }
        return !$forceRefresh && !$auth->shouldRefresh();
    }

    private function sentTokenVersion(PipelineContext $context): ?string
    {
        $prepared = $context->response?->request ?? $context->preparedRequest;
        foreach ($prepared?->headers ?? [] as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0 && str_starts_with($value, 'Bearer ')) {
                return hash('sha256', substr($value, 7));
            }
        }
        return null;
    }
}
