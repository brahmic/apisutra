<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\MetadataIsolation;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/defaults')]
#[Returns(DefaultsDto::class)]
final class DefaultsRequest extends AbstractRequest
{
}
