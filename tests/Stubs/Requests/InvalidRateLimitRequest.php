<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Behavior\RateLimit;

#[RateLimit(limit: 0, period: 60)]
final class InvalidRateLimitRequest extends RetryPolicyRequest
{
}
