<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Behavior\Retry;

#[Retry(enabled: false, baseDelay: 0)]
final class RetryDisabledRequest extends RetryPolicyRequest {}
