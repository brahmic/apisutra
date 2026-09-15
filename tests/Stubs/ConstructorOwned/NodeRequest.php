<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\ConstructorOwned;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/report')]
#[Returns(NodeDto::class, unwrap: 'data')]
final class NodeRequest extends AbstractRequest
{
}
