<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Config;

use Brahmic\ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Psr\SimpleCache\CacheInterface;

final readonly class RateLimitConfig
{
    public function __construct(
        public int $limit = 100,
        public int $period = 60,
        public RateLimitBehavior $behavior = RateLimitBehavior::Wait,
        public ?CacheInterface $store = null,
        public ?string $key = null,
    ) {
        if ($limit < 1) {
            throw new ConfigurationException('RateLimitConfig::limit должен быть положительным');
        }
        if ($period < 1 || $period > intdiv(PHP_INT_MAX, 1_000_000)) {
            throw new ConfigurationException('RateLimitConfig::period должен быть положительным и представимым в микросекундах штатного sleeper');
        }
    }
}
