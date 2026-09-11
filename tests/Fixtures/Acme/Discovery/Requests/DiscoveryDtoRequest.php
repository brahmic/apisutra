<?php

declare(strict_types=1);

namespace Acme\Discovery\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;

#[Get('/discovery-dto')]
#[Returns(SimpleResponseDto::class)]
final class DiscoveryDtoRequest extends AbstractRequest
{
}
