<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\StringIdentifierDto;

#[Get('/identifiers')]
#[Returns(SimpleResponseDto::class, unwrap: 'data.0', type: StringIdentifierDto::class)]
final class IndexedIdentifierRequest extends AbstractRequest
{
}
