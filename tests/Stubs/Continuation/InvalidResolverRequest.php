<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Continuation;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\ContinuationResult;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\Dto\ContinuationFinalDto;
use stdClass;

#[Get('/start')]
#[ContinuationResult(ContinuationFinalDto::class, stateResolver: stdClass::class)]
final class InvalidResolverRequest extends AbstractRequest
{
}
