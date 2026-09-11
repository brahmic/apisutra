<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Request;

use Brahmic\ApiSutra\Config\RateLimitConfig;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Enums\Auth\AuthOverride;
use Brahmic\ApiSutra\Enums\Cache\CacheMode;
use Brahmic\ApiSutra\Enums\Continuation\ContinuationMode;
use Brahmic\ApiSutra\Enums\Execution\RequestRole;
use Brahmic\ApiSutra\Enums\Request\CredentialsMergeMode;
use Brahmic\ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use Brahmic\ApiSutra\Pagination\PaginationRule;
use Brahmic\ApiSutra\VO\Cache\CacheOverride;

/**
 * Набор runtime-опций запроса.
 *
 * Нюансы:
 * - immutable: каждый with* возвращает новый экземпляр;
 * - null означает "не переопределено";
 * - опции действуют только для текущего вызова.
 * - withContinuationMode()/asProvider* меняют только core-mode,
 *   фактический provider-mapping выполняется ContinuationModeApplicatorInterface.
 *
 * Рекомендации:
 * - стабильные настройки задавать в атрибутах/ClientConfig;
 * - runtime-override применять точечно.
 *
 * @see docs/guides/requests.md
 * @see docs/guides/provider-async-await.md
 */
final readonly class RequestOptions
{
    /**
     * Сервисный конструктор (предпочтительнее использовать factory/with*).
     *
     * @param array<string, string> $headersOverride
     */
    public function __construct(
        private ?string $baseUrlOverride,
        private ?int $cacheTtlOverride,
        private ?CacheMode $cacheModeOverride,
        private ?bool $retryEnabledOverride,
        private ?int $retryAttemptsOverride,
        private ?AuthOverride $authOverride,
        private ?string $authScopeOverride,
        private ?int $delayOverride,
        private ?string $idempotencyKey,
        private ?RateLimitConfig $rateLimitOverride,
        private ?int $timeoutOverride,
        private ?int $connectTimeoutOverride,
        private ?string $traceIdOverride,
        private ?RequestRole $roleOverride,
        private ?bool $rateLimitDisabledOverride,
        private ?PaginationRule $paginationRuleOverride,
        private array $headersOverride,
        private ?bool $credentialsEnrichmentEnabledOverride,
        private ?CredentialsMergeMode $credentialsMergeModeOverride,
        private ?string $credentialsScopeOverride,
        private ?ContinuationMode $continuationModeOverride,
        private ?string $cacheScopeOverride = null,
    ) {
        if ($cacheScopeOverride !== null && trim($cacheScopeOverride) === '') {
            throw new ConfigurationException('Пространство кеша не должно быть пустым');
        }
    }

    /**
     * Пустой набор опций без переопределений.
     */
    public static function empty(): self
    {
        return new self(
            baseUrlOverride: null,
            cacheTtlOverride: null,
            cacheModeOverride: null,
            retryEnabledOverride: null,
            retryAttemptsOverride: null,
            authOverride: null,
            authScopeOverride: null,
            delayOverride: null,
            idempotencyKey: null,
            rateLimitOverride: null,
            timeoutOverride: null,
            connectTimeoutOverride: null,
            traceIdOverride: null,
            roleOverride: null,
            rateLimitDisabledOverride: null,
            paginationRuleOverride: null,
            headersOverride: [],
            credentialsEnrichmentEnabledOverride: null,
            credentialsMergeModeOverride: null,
            credentialsScopeOverride: null,
            continuationModeOverride: null,
        );
    }

    /**
     * Создать новый экземпляр с переопределёнными значениями.
     *
     * @param array<string, mixed> $overrides
     */
    private function with(array $overrides): self
    {
        return new self(
            baseUrlOverride: $overrides['baseUrlOverride'] ?? $this->baseUrlOverride,
            cacheTtlOverride: $overrides['cacheTtlOverride'] ?? $this->cacheTtlOverride,
            cacheModeOverride: $overrides['cacheModeOverride'] ?? $this->cacheModeOverride,
            retryEnabledOverride: $overrides['retryEnabledOverride'] ?? $this->retryEnabledOverride,
            retryAttemptsOverride: $overrides['retryAttemptsOverride'] ?? $this->retryAttemptsOverride,
            authOverride: $overrides['authOverride'] ?? $this->authOverride,
            authScopeOverride: array_key_exists('authScopeOverride', $overrides)
                ? $overrides['authScopeOverride']
                : $this->authScopeOverride,
            delayOverride: $overrides['delayOverride'] ?? $this->delayOverride,
            idempotencyKey: $overrides['idempotencyKey'] ?? $this->idempotencyKey,
            rateLimitOverride: $overrides['rateLimitOverride'] ?? $this->rateLimitOverride,
            timeoutOverride: $overrides['timeoutOverride'] ?? $this->timeoutOverride,
            connectTimeoutOverride: $overrides['connectTimeoutOverride'] ?? $this->connectTimeoutOverride,
            traceIdOverride: $overrides['traceIdOverride'] ?? $this->traceIdOverride,
            roleOverride: $overrides['roleOverride'] ?? $this->roleOverride,
            rateLimitDisabledOverride: $overrides['rateLimitDisabledOverride'] ?? $this->rateLimitDisabledOverride,
            paginationRuleOverride: $overrides['paginationRuleOverride'] ?? $this->paginationRuleOverride,
            headersOverride: $overrides['headersOverride'] ?? $this->headersOverride,
            credentialsEnrichmentEnabledOverride: $overrides['credentialsEnrichmentEnabledOverride'] ?? $this->credentialsEnrichmentEnabledOverride,
            credentialsMergeModeOverride: $overrides['credentialsMergeModeOverride'] ?? $this->credentialsMergeModeOverride,
            credentialsScopeOverride: $overrides['credentialsScopeOverride'] ?? $this->credentialsScopeOverride,
            continuationModeOverride: $overrides['continuationModeOverride'] ?? $this->continuationModeOverride,
            cacheScopeOverride: $overrides['cacheScopeOverride'] ?? $this->cacheScopeOverride,
        );
    }

    /**
     * Переопределить baseUrl только для текущего вызова.
     */
    public function withBaseUrl(string $url): self
    {
        return $this->with(['baseUrlOverride' => $url]);
    }

    /**
     * Включить кеширование и опционально задать TTL.
     */
    public function withCache(?int $ttl = null): self
    {
        return $this->with([
            'cacheTtlOverride' => $ttl,
            'cacheModeOverride' => CacheMode::Enabled,
        ]);
    }

    /** Пространство provider/identity/tenant для текущего исполнения. */
    public function withCacheScope(string $scope): self
    {
        return $this->with(['cacheScopeOverride' => $scope]);
    }

    public function getCacheScopeOverride(): ?string
    {
        return $this->cacheScopeOverride;
    }

    /**
     * Отключить кеширование для запроса.
     */
    public function withoutCache(): self
    {
        return $this->with([
            'cacheModeOverride' => CacheMode::Disabled,
        ]);
    }

    /**
     * Выполнить запрос без чтения из кеша, но с записью.
     */
    public function withCacheWriteOnly(?int $ttl = null): self
    {
        return $this->with([
            'cacheTtlOverride' => $ttl,
            'cacheModeOverride' => CacheMode::WriteOnly,
        ]);
    }

    /**
     * Выполнить запрос с чтением из кеша, но без записи.
     */
    public function withCacheReadOnly(?int $ttl = null): self
    {
        return $this->with([
            'cacheTtlOverride' => $ttl,
            'cacheModeOverride' => CacheMode::ReadOnly,
        ]);
    }

    /**
     * Включить retry и задать число попыток (для идемпотентных запросов).
     */
    public function withRetry(int $attempts): self
    {
        return $this->with([
            'retryEnabledOverride' => true,
            'retryAttemptsOverride' => $attempts,
        ]);
    }

    /**
     * Отключить retry для запроса.
     */
    public function withoutRetry(): self
    {
        return $this->with(['retryEnabledOverride' => false]);
    }

    /**
     * Отключить auth для запроса, заменив предыдущий runtime-выбор.
     */
    public function withoutAuth(): self
    {
        return $this->with([
            'authOverride' => AuthOverride::Disable,
            'authScopeOverride' => null,
        ]);
    }

    /**
     * Включить auth для запроса (не пробивает #[NoAuth]).
     */
    public function withAuth(): self
    {
        return $this->with([
            'authOverride' => AuthOverride::Enable,
            'authScopeOverride' => null,
        ]);
    }

    /**
     * Включить auth и выбрать scope (не пробивает #[NoAuth]).
     */
    public function withAuthScope(string $scope): self
    {
        return $this->with([
            'authOverride' => AuthOverride::Enable,
            'authScopeOverride' => $scope,
        ]);
    }

    /**
     * Принудительно включить auth (пробивает #[NoAuth]/withoutAuth()).
     *
     * Рекомендация: использовать только для исключительных кейсов.
     */
    public function forceAuth(): self
    {
        return $this->with([
            'authOverride' => AuthOverride::ForceEnable,
            'authScopeOverride' => null,
        ]);
    }

    /**
     * Принудительно включить auth и выбрать scope (пробивает #[NoAuth]/withoutAuth()).
     *
     * Рекомендация: использовать только для исключительных кейсов.
     */
    public function forceAuthScope(string $scope): self
    {
        return $this->with([
            'authOverride' => AuthOverride::ForceEnable,
            'authScopeOverride' => $scope,
        ]);
    }

    /**
     * Добавить задержку перед запросом (мс).
     */
    public function withDelay(int $ms): self
    {
        return $this->with(['delayOverride' => $ms]);
    }

    /**
     * Убрать задержку запроса.
     */
    public function withoutDelay(): self
    {
        return $this->with(['delayOverride' => 0]);
    }

    /**
     * Установить ключ идемпотентности.
     */
    public function withIdempotencyKey(string $key): self
    {
        return $this->with(['idempotencyKey' => $key]);
    }

    /**
     * Переопределить rate limit для запроса.
     */
    public function withRateLimit(
        int $limit,
        int $period,
        RateLimitBehavior $behavior = RateLimitBehavior::Wait,
        ?string $key = null,
    ): self {
        return $this->with([
            'rateLimitOverride' => new RateLimitConfig($limit, $period, $behavior, null, $key),
            'rateLimitDisabledOverride' => false,
        ]);
    }

    /**
     * Отключить rate limit для запроса.
     */
    public function withoutRateLimit(): self
    {
        return $this->with([
            'rateLimitOverride' => null,
            'rateLimitDisabledOverride' => true,
        ]);
    }

    /**
     * Переопределить таймауты запроса.
     */
    public function withTimeout(int $seconds, ?int $connectTimeout = null): self
    {
        return $this->with([
            'timeoutOverride' => $seconds,
            'connectTimeoutOverride' => $connectTimeout,
        ]);
    }

    /**
     * Установить traceId для корреляции запросов.
     */
    public function withTraceId(string $traceId): self
    {
        return $this->with(['traceIdOverride' => $traceId]);
    }

    /**
     * Явно задать роль запроса в пайплайне (обычно используется ядром).
     */
    public function withRole(RequestRole $role): self
    {
        return $this->with(['roleOverride' => $role]);
    }

    /**
     * Добавить или переопределить header для запроса.
     */
    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headersOverride;
        $headers[$name] = $value;

        return $this->with(['headersOverride' => $headers]);
    }

    /**
     * Переопределить правило пагинации.
     */
    public function withPaginationRule(PaginationRule $rule): self
    {
        return $this->with(['paginationRuleOverride' => $rule]);
    }

    /**
     * Явно включить/выключить provider credentials enrichment.
     */
    public function withCredentialsEnrichment(bool $enabled = true): self
    {
        return $this->with(['credentialsEnrichmentEnabledOverride' => $enabled]);
    }

    /**
     * Явно отключить provider credentials enrichment.
     */
    public function withoutCredentialsEnrichment(): self
    {
        return $this->withCredentialsEnrichment(false);
    }

    /**
     * Переопределить merge mode provider credentials enrichment.
     */
    public function withCredentialsMergeMode(CredentialsMergeMode $mode): self
    {
        return $this->with(['credentialsMergeModeOverride' => $mode]);
    }

    /**
     * Переопределить scope provider credentials enrichment.
     */
    public function withCredentialsScope(string $scope): self
    {
        return $this->with(['credentialsScopeOverride' => $scope]);
    }

    /**
     * Переопределить режим provider-async для текущего запроса.
     */
    public function withContinuationMode(ContinuationMode $mode): self
    {
        return $this->with(['continuationModeOverride' => $mode]);
    }

    /**
     * Получить override baseUrl (null = не переопределено).
     */
    public function getBaseUrlOverride(): ?string
    {
        return $this->baseUrlOverride;
    }

    /**
     * Получить override кеша (null = не переопределено).
     */
    public function getCacheOverride(): CacheOverride
    {
        return new CacheOverride(
            mode: $this->cacheModeOverride,
            ttl: $this->cacheTtlOverride,
        );
    }

    /**
     * Получить override retry (null = не переопределено).
     *
     * @return array{enabled: ?bool, attempts: ?int}
     */
    public function getRetryOverride(): array
    {
        return [
            'enabled' => $this->retryEnabledOverride,
            'attempts' => $this->retryAttemptsOverride,
        ];
    }

    /**
     * Получить override поведения auth (null = не переопределено).
     */
    public function getAuthOverride(): ?AuthOverride
    {
        return $this->authOverride;
    }

    /**
     * Получить override scope для auth (null = не переопределено).
     */
    public function getAuthScopeOverride(): ?string
    {
        return $this->authScopeOverride;
    }

    /**
     * Получить override задержки (мс).
     */
    public function getDelayOverride(): ?int
    {
        return $this->delayOverride;
    }

    /**
     * Получить ключ идемпотентности.
     */
    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    /**
     * Получить override rate limit (null = не переопределено).
     */
    public function getRateLimitOverride(): ?RateLimitConfig
    {
        return $this->rateLimitOverride;
    }

    /**
     * Получить флаг отключения rate limit.
     */
    public function getRateLimitDisabledOverride(): ?bool
    {
        return $this->rateLimitDisabledOverride;
    }

    /**
     * Получить override timeout (секунды).
     */
    public function getTimeoutOverride(): ?int
    {
        return $this->timeoutOverride;
    }

    /**
     * Получить override connect timeout (секунды).
     */
    public function getConnectTimeoutOverride(): ?int
    {
        return $this->connectTimeoutOverride;
    }

    /**
     * Получить override traceId.
     */
    public function getTraceIdOverride(): ?string
    {
        return $this->traceIdOverride;
    }

    /**
     * Получить override роли запроса.
     */
    public function getRoleOverride(): ?RequestRole
    {
        return $this->roleOverride;
    }

    /**
     * Получить headers, которые будут добавлены/переопределены.
     *
     * @return array<string, string>
     */
    public function getHeadersOverride(): array
    {
        return $this->headersOverride;
    }

    /**
     * Получить override правила пагинации.
     */
    public function getPaginationRuleOverride(): ?PaginationRule
    {
        return $this->paginationRuleOverride;
    }

    /**
     * Получить override enabled-флага provider credentials enrichment.
     */
    public function getCredentialsEnrichmentEnabledOverride(): ?bool
    {
        return $this->credentialsEnrichmentEnabledOverride;
    }

    /**
     * Получить override merge mode provider credentials enrichment.
     */
    public function getCredentialsMergeModeOverride(): ?CredentialsMergeMode
    {
        return $this->credentialsMergeModeOverride;
    }

    /**
     * Получить override scope provider credentials enrichment.
     */
    public function getCredentialsScopeOverride(): ?string
    {
        return $this->credentialsScopeOverride;
    }

    /**
     * Получить override режима provider-async.
     */
    public function getContinuationModeOverride(): ?ContinuationMode
    {
        return $this->continuationModeOverride;
    }
}
