<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Response\RawResponse;

#[RawResponse]
final class RawStringRequest extends RetryPolicyRequest
{
}
