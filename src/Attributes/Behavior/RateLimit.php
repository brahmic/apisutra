<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Attributes\Behavior;

use Attribute;
use Brahmic\ApiSutra\Enums\RateLimiting\RateLimitBehavior;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class RateLimit
{
    public function __construct(
        public int $limit,
        public int $period,
        public RateLimitBehavior $behavior = RateLimitBehavior::Wait,
        public ?string $key = null,
    ) {}
}
