<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Behavior\Retry;

#[Retry(baseDelay: 0, safe: true)]
final class RetryAllowedRequest extends RetryPolicyRequest {}
