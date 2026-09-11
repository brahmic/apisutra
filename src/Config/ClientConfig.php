<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Config;

use Brahmic\ApiSutra\Diagnostics\RedactionPolicy;
use Brahmic\ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Auth\AuthPolicyInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Catalog\ProviderCatalogRegistryInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Continuation\ContinuationModeApplicatorInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Extensions\ExtensionInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Serialization\DtoSerializationProfileInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Serialization\RequestPartsEnricherInterface;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Configuration\NamingStrategy;
use Brahmic\ApiSutra\Enums\Continuation\ContinuationMode;
use Brahmic\ApiSutra\Enums\Http\QueryArrayFormat;
use Brahmic\ApiSutra\Enums\Serialization\EnumOutput;
use Brahmic\ApiSutra\Enums\Serialization\BooleanFormat;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Pagination\PaginationRule;
use Brahmic\ApiSutra\Result\ContinuationTokenExtractorInterface;
use Brahmic\ApiSutra\Response\ClientResponseFactoryInterface;
use Brahmic\ApiSutra\Result\ResolvedResultFactoryInterface;
use Brahmic\ApiSutra\Result\ResultMetaExtractorInterface;
use Brahmic\ApiSutra\Support\ContainerProviderRegistry;
use Brahmic\ApiSutra\VO\Errors\ClientErrorMapperInterface;
use Brahmic\ApiSutra\VO\Errors\ErrorContextFactoryInterface;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\SimpleCache\CacheInterface;
use Throwable;

/**
 * Единая immutable-конфигурация клиента ApiSutra.
 *
 * Назначение:
 * - задаёт глобальные дефолты поведения пайплайна;
 * - передаёт расширения и стратегии (auth/error/continuation и т.д.);
 * - служит нижним уровнем приоритета для request-атрибутов и runtime-overrides.
 *
 * Нюансы continuation-блока:
 * - continuationTokenExtractor: единая стратегия извлечения token из ExecutionResult;
 * - resultMetaExtractor: единая стратегия извлечения provider envelope meta из ExecutionResult;
 * - providerCatalogRegistry: read-only статические provider catalogs (DX metadata), не связанные с runtime result meta;
 * - defaultContinuationMode: дефолт mode для optional async;
 * - defaultPollRequest: poll-request fallback для awaitByToken/await;
 * - continuationModeApplicator: provider-specific mapping mode в transport-поля.
 *
 * @see docs/guides/client-config/README.md
 * @see docs/guides/client-config/responses-errors.md
 * @see docs/guides/provider-async-await.md
 */
final readonly class ClientConfig
{
    public ?CacheInterface $cache;
    public ?CacheConfig $cacheConfig;
    public PaginationRule $paginationRule;
    public DateTimeSerializationPolicy $requestDateTime;

    /**
     * @param array<string, CastInterface|class-string<CastInterface>> $casts Касты по типам.
     * @param array<ExtensionInterface> $extensions Расширения клиента.
     * @param array<RequestPartsEnricherInterface> $requestEnrichers Enrichers request parts до finalize payload.
     * @param ContinuationTokenExtractorInterface|null $continuationTokenExtractor Извлечение continuation token из результата.
     * @param ResultMetaExtractorInterface|null $resultMetaExtractor Извлечение provider meta из результата.
     * @param ProviderCatalogRegistryInterface|null $providerCatalogRegistry Read-only provider catalogs SDK.
     * @param string|null $defaultPollRequest Класс poll-request по умолчанию для awaitByToken/await.
     * @param array<string, AuthenticatorInterface> $authScopes Ключи рекомендуется задавать через enum.
     */
    public function __construct(
        public string $baseUrl,
        public ?AuthenticatorInterface $auth = null,
        public array $authScopes = [],
        public ?AuthPolicyInterface $authPolicy = null,
        public bool $authRetryOn401 = true,
        public int $authRetryAttempts = 1,
        public ?LoggerInterface $logger = null,
        public string $logLevel = LogLevel::INFO,
        CacheInterface|CacheConfig|null $cache = null,
        ?CacheConfig $cacheConfig = null,
        public int $timeout = 30,
        public int $connectTimeout = 10,
        public ?RetryConfig $retry = null,
        public ?RateLimitConfig $rateLimit = null,
        public ?PoolConfig $pool = null,
        public QueryArrayFormat $queryArrayFormat = QueryArrayFormat::Brackets,
        public bool $serializeNulls = false,
        public NamingStrategy $namingStrategy = NamingStrategy::None,
        public array $casts = [],
        public ?DtoSerializationProfileInterface $dtoSerializationProfile = null,
        public ?DtoSerializationPolicy $wireBodySerializationPolicy = null,
        public EnumOutput $requestPartsEnumOutput = EnumOutput::Value,
        public bool $requestPartsStrictEnums = false,
        ?DateTimeSerializationPolicy $requestDateTime = null,
        public int $delay = 0,
        public bool $throwOnErrors = false,
        public bool $debug = false,
        public Environment $environment = Environment::Production,
        public string $idempotencyHeader = 'Idempotency-Key',
        public array $extensions = [],
        public ?PaginationConfig $paginationConfig = null,
        public ?ArchiveConfig $archive = null,
        ?PaginationRule $paginationRule = null,
        public ?ResolvedResultFactoryInterface $resolvedResultFactory = null,
        public ?ClientErrorMapperInterface $errorMapper = null,
        public ?ErrorContextFactoryInterface $errorContextFactory = null,
        public ?ClientResponseFactoryInterface $responseFactory = null,
        public ?ContainerProviderInterface $containerProvider = null,
        public array $requestEnrichers = [],
        public ?CredentialsEnrichmentConfig $credentialsConfig = null,
        public ?ContinuationTokenExtractorInterface $continuationTokenExtractor = null,
        public ?ResultMetaExtractorInterface $resultMetaExtractor = null,
        public ?ProviderCatalogRegistryInterface $providerCatalogRegistry = null,
        public ContinuationMode $defaultContinuationMode = ContinuationMode::Auto,
        public ?string $defaultPollRequest = null,
        public ?ContinuationModeApplicatorInterface $continuationModeApplicator = null,
        public RedactionPolicy $redaction = new RedactionPolicy(),
        public BooleanFormat $textBooleanFormat = BooleanFormat::Numeric,
    ) {
        if ($cache instanceof CacheConfig) {
            $this->cacheConfig = $cache;
            $this->cache = $cache->store;
        } else {
            $this->cache = $cache;
            $this->cacheConfig = $cacheConfig;
        }

        $this->paginationRule = $paginationRule ?? PaginationRule::single();
        $this->requestDateTime = $requestDateTime ?? new DateTimeSerializationPolicy();
        $this->validate();
    }

    /**
     * Конструктор с авто-настройками для Laravel
     * @param array<string, mixed> $overrides
     */
    public static function fromLaravel(array $overrides): self
    {
        $provider = $overrides['containerProvider'] ?? null;
        $provider = $provider instanceof ContainerProviderInterface ? $provider : null;

        $defaults = [
            'baseUrl' => (string) ($overrides['baseUrl'] ?? ''),
            'debug' => $overrides['debug'] ?? self::resolveLaravelDebug($provider),
            'environment' => $overrides['environment'] ?? self::resolveLaravelEnvironment($provider),
        ];

        return new self(...array_merge($defaults, $overrides));
    }

    /**
     * Создать с переопределениями
     */
    public function with(mixed ...$overrides): self
    {
        $data = [
            'baseUrl' => $this->baseUrl,
            'auth' => $this->auth,
            'authScopes' => $this->authScopes,
            'authPolicy' => $this->authPolicy,
            'authRetryOn401' => $this->authRetryOn401,
            'authRetryAttempts' => $this->authRetryAttempts,
            'logger' => $this->logger,
            'logLevel' => $this->logLevel,
            'cache' => $this->cacheConfig ?? $this->cache,
            'cacheConfig' => $this->cacheConfig,
            'timeout' => $this->timeout,
            'connectTimeout' => $this->connectTimeout,
            'retry' => $this->retry,
            'rateLimit' => $this->rateLimit,
            'pool' => $this->pool,
            'queryArrayFormat' => $this->queryArrayFormat,
            'serializeNulls' => $this->serializeNulls,
            'namingStrategy' => $this->namingStrategy,
            'casts' => $this->casts,
            'dtoSerializationProfile' => $this->dtoSerializationProfile,
            'wireBodySerializationPolicy' => $this->wireBodySerializationPolicy,
            'requestPartsEnumOutput' => $this->requestPartsEnumOutput,
            'requestPartsStrictEnums' => $this->requestPartsStrictEnums,
            'requestDateTime' => $this->requestDateTime,
            'delay' => $this->delay,
            'throwOnErrors' => $this->throwOnErrors,
            'debug' => $this->debug,
            'environment' => $this->environment,
            'idempotencyHeader' => $this->idempotencyHeader,
            'extensions' => $this->extensions,
            'paginationConfig' => $this->paginationConfig,
            'archive' => $this->archive,
            'paginationRule' => $this->paginationRule,
            'resolvedResultFactory' => $this->resolvedResultFactory,
            'errorMapper' => $this->errorMapper,
            'errorContextFactory' => $this->errorContextFactory,
            'responseFactory' => $this->responseFactory,
            'containerProvider' => $this->containerProvider,
            'requestEnrichers' => $this->requestEnrichers,
            'credentialsConfig' => $this->credentialsConfig,
            'continuationTokenExtractor' => $this->continuationTokenExtractor,
            'resultMetaExtractor' => $this->resultMetaExtractor,
            'providerCatalogRegistry' => $this->providerCatalogRegistry,
            'defaultContinuationMode' => $this->defaultContinuationMode,
            'defaultPollRequest' => $this->defaultPollRequest,
            'continuationModeApplicator' => $this->continuationModeApplicator,
            'redaction' => $this->redaction,
            'textBooleanFormat' => $this->textBooleanFormat,
        ];

        foreach ($overrides as $key => $value) {
            $data[$key] = $value;
        }

        return new self(...$data);
    }

    private static function resolveLaravelDebug(?ContainerProviderInterface $provider = null): bool
    {
        $provider = ContainerProviderRegistry::resolve($provider);
        $debug = $provider->isDebug();
        if (is_bool($debug)) {
            return $debug;
        }

        return false;
    }

    private static function resolveLaravelEnvironment(?ContainerProviderInterface $provider = null): Environment
    {
        $provider = ContainerProviderRegistry::resolve($provider);
        $env = $provider->environment();
        if (is_string($env)) {
            return match (strtolower($env)) {
                'local' => Environment::Local,
                'testing' => Environment::Testing,
                'staging' => Environment::Staging,
                default => Environment::Production,
            };
        }

        return Environment::Production;
    }

    public function getPaginationRule(): PaginationRule
    {
        return $this->paginationRule;
    }

    /**
     * Валидировать параметры клиента.
     */
    private function validate(): void
    {
        if (trim($this->baseUrl) === '') {
            throw new ConfigurationException('ClientConfig.baseUrl не должен быть пустым');
        }

        if ($this->timeout < 0) {
            throw new ConfigurationException('ClientConfig.timeout должен быть >= 0');
        }

        if ($this->connectTimeout < 0) {
            throw new ConfigurationException('ClientConfig.connectTimeout должен быть >= 0');
        }

        if ($this->delay < 0) {
            throw new ConfigurationException('ClientConfig.delay должен быть >= 0');
        }

        if ($this->authRetryAttempts < 0) {
            throw new ConfigurationException('ClientConfig.authRetryAttempts должен быть >= 0');
        }

        if (trim($this->idempotencyHeader) === '') {
            throw new ConfigurationException('ClientConfig.idempotencyHeader не должен быть пустым');
        }

        foreach ($this->casts as $type => $cast) {
            if ($cast instanceof CastInterface) {
                continue;
            }

            if (is_string($cast)) {
                if (!class_exists($cast)) {
                    throw new ConfigurationException("ClientConfig.casts[{$type}] класс {$cast} не найден");
                }

                if (!is_subclass_of($cast, CastInterface::class)) {
                    throw new ConfigurationException("ClientConfig.casts[{$type}] {$cast} должен реализовывать CastInterface");
                }

                continue;
            }

            throw new ConfigurationException("ClientConfig.casts[{$type}] содержит недопустимое значение");
        }

        foreach ($this->requestEnrichers as $index => $enricher) {
            if (!$enricher instanceof RequestPartsEnricherInterface) {
                throw new ConfigurationException(
                    "ClientConfig.requestEnrichers[{$index}] должен реализовывать RequestPartsEnricherInterface",
                );
            }
        }

        if ($this->credentialsConfig !== null) {
            foreach ($this->credentialsConfig->scopes as $scope => $config) {
                if (!$config instanceof CredentialsScopeConfig) {
                    throw new ConfigurationException(
                        "ClientConfig.credentialsConfig.scopes[{$scope}] должен быть CredentialsScopeConfig",
                    );
                }
            }
        }

        if ($this->defaultPollRequest !== null) {
            $pollRequestClass = trim($this->defaultPollRequest);
            if ($pollRequestClass === '') {
                throw new ConfigurationException('ClientConfig.defaultPollRequest не должен быть пустым');
            }

            if (!class_exists($pollRequestClass)) {
                throw new ConfigurationException('ClientConfig.defaultPollRequest класс не найден: ' . $pollRequestClass);
            }

            if (!is_subclass_of($pollRequestClass, RequestInterface::class)) {
                throw new ConfigurationException(
                    'ClientConfig.defaultPollRequest должен реализовывать RequestInterface: ' . $pollRequestClass,
                );
            }
        }

        if ($this->requestPartsEnumOutput === EnumOutput::Object) {
            throw new ConfigurationException('ClientConfig.requestPartsEnumOutput не может быть Object для query/header/path');
        }

        $this->validateTimezone($this->requestDateTime->timezone, 'ClientConfig.requestDateTime.timezone');
    }

    private function validateTimezone(?string $timezone, string $path): void
    {
        if ($timezone === null) {
            return;
        }

        try {
            new DateTimeZone($timezone);
        } catch (Throwable) {
            throw new ConfigurationException($path . ' содержит недопустимую timezone: ' . $timezone);
        }
    }
}



