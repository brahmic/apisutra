<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Response\RawResponse;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;

#[RawResponse]
#[Returns(SimpleResponseDto::class)]
final class RawDtoRequest extends RetryPolicyRequest
{
}
