<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Behavior\RateLimit;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\RateLimiting\RateLimitBehavior;

#[Get('/rate-limit-key')]
#[RateLimit(limit: 1, period: 60, behavior: RateLimitBehavior::Throw, key: 'attr-key')]
final class RateLimitKeyRequest extends AbstractRequest
{
}
