<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Behavior\Timeout;

#[Timeout(seconds: 8, connectTimeout: 4)]
final class TimeoutPolicyRequest extends RetryPolicyRequest {}
