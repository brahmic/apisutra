<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Support;

use Brahmic\ApiSutra\Config\CacheConfig;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\RateLimitConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;
use Psr\SimpleCache\CacheInterface;

final class TestClientFactory
{
    /**
     * @param array<string, mixed> $overrides
     */
    public static function make(array $responses = [], array $overrides = []): TestClient
    {
        $cache = self::resolveCacheStore($overrides['cache'] ?? null, $overrides['cacheConfig'] ?? null);
        $cacheConfig = $overrides['cacheConfig'] ?? new CacheConfig(store: $cache, ttl: 60, prefix: 'tests');
        $rateLimit = $overrides['rateLimit'] ?? new RateLimitConfig(
            limit: 5,
            period: 60,
            behavior: RateLimitBehavior::Wait,
            store: $cache,
        );

        $config = new ClientConfig(
            baseUrl: (string) ($overrides['baseUrl'] ?? 'https://provider.test'),
            cache: $cacheConfig,
            rateLimit: $rateLimit,
            environment: $overrides['environment'] ?? Environment::Testing,
        );

        if ($overrides !== []) {
            $config = $config->with(...$overrides);
        }

        $transport = new MockTransport();
        if ($responses !== []) {
            $transport->fake($responses);
        }

        return new TestClient($config, $transport);
    }

    private static function resolveCacheStore(?CacheInterface $cache, mixed $cacheConfig): CacheInterface
    {
        if ($cacheConfig instanceof CacheConfig && $cacheConfig->store instanceof CacheInterface) {
            return $cacheConfig->store;
        }

        return $cache ?? new ArrayCache();
    }
}
