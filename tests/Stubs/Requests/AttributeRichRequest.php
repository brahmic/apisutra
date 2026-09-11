<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Behavior\Cache;
use Brahmic\ApiSutra\Attributes\Behavior\Execution;
use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Behavior\Idempotent;
use Brahmic\ApiSutra\Attributes\Behavior\NoAuth;
use Brahmic\ApiSutra\Attributes\Behavior\RateLimit;
use Brahmic\ApiSutra\Attributes\Behavior\Timeout;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Cache\CacheMode;
use Brahmic\ApiSutra\Enums\Execution\ExecutionMode;
use Brahmic\ApiSutra\Enums\Execution\FailStrategy;
use Brahmic\ApiSutra\Enums\RateLimiting\RateLimitBehavior;

#[Get('/attributes')]
#[NoAuth]
#[Timeout(seconds: 5, connectTimeout: 2)]
#[RateLimit(limit: 10, period: 60, behavior: RateLimitBehavior::Wait)]
#[Idempotent(header: 'Idempotency-Key')]
#[Execution(mode: ExecutionMode::Sequential, failStrategy: FailStrategy::FailAll)]
#[Cache(ttl: 300, mode: CacheMode::Enabled, key: 'attr-cache-key')]
final class AttributeRichRequest extends AbstractRequest
{
    public function __construct(
        public string $payload,
    ) {}
}
