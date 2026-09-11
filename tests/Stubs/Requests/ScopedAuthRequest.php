<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Request\AuthScope;
use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;

#[Get('/auth-scope')]
#[Returns(SimpleResponseDto::class)]
#[AuthScope('system')]
final class ScopedAuthRequest extends AbstractRequest
{
}
