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

final class TestClientFactory
{
    /**
     * @param array<string, mixed> $overrides
     */
    public static function make(array $responses = [], array $overrides = []): TestClient
    {
        $cacheStore = array_key_exists('cacheStore', $overrides) ? $overrides['cacheStore'] : new ArrayCache();
        $cacheConfig = array_key_exists('cacheConfig', $overrides)
            ? $overrides['cacheConfig'] : new CacheConfig(ttl: 60, prefix: 'tests');
        $rateLimit = $overrides['rateLimit'] ?? new RateLimitConfig(
            limit: 5,
            period: 60,
            behavior: RateLimitBehavior::Wait,
            store: $cacheStore,
        );

        $config = new ClientConfig(
            baseUrl: (string) ($overrides['baseUrl'] ?? 'https://provider.test'),
            cacheStore: $cacheStore,
            cacheConfig: $cacheConfig,
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
}
