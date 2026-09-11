<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Behavior\Retry;

#[Retry(safe: false)]
final class RetryDeniedRequest extends RetryPolicyRequest {}
