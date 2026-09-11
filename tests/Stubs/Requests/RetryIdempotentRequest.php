<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Behavior\Idempotent;

#[Idempotent]
final class RetryIdempotentRequest extends RetryPolicyRequest {}
