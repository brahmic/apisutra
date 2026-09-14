<?php

declare(strict_types=1);

namespace MaxSutraAudit;

use Brahmic\ApiSutra\Attributes\Behavior\RateLimit;
use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\RateLimiting\RateLimitBehavior;

#[Get('/reports')]
#[RateLimit(limit: 1, period: 60, behavior: RateLimitBehavior::Throw)]
final class ReadLimited extends AbstractRequest {}
