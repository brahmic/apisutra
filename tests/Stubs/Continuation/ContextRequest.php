<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Continuation;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\ContinuationResult;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Continuation\ContinuationMode;
use Brahmic\ApiSutra\Tests\Stubs\Requests\ContinuationPollRequest;

#[Get('/continuation/context')]
#[ContinuationResult(
    ContextFinalDto::class,
    unwrap: 'data',
    pollRequest: ContinuationPollRequest::class,
    defaultMode: ContinuationMode::Sync,
    stateResolver: ContextStateResolver::class,
)]
final class ContextRequest extends AbstractRequest
{
}
