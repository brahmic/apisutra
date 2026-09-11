<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Config;

use Brahmic\ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use Psr\SimpleCache\CacheInterface;

final readonly class RateLimitConfig
{
    public function __construct(
        public int $limit = 100,
        public int $period = 60,
        public RateLimitBehavior $behavior = RateLimitBehavior::Wait,
        public ?CacheInterface $store = null,
        public ?string $key = null,
    ) {}
}