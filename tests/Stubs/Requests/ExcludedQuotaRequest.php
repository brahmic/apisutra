<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Behavior\RateLimit;
use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\RateLimiting\RateLimitBehavior;

#[Get('/quota')]
#[RateLimit(limit: 1, period: 60, behavior: RateLimitBehavior::Throw, includeClientQuota: false)]
final class ExcludedQuotaRequest extends AbstractRequest
{
}
