<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Continuation;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\ContinuationResult;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\Dto\ContinuationFinalDto;

#[Get('/start')]
#[ContinuationResult(ContinuationFinalDto::class, unwrap: 'data', stateResolver: TerminalStateResolver::class)]
final class TerminalRequest extends AbstractRequest
{
}
