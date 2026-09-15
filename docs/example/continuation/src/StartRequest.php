<?php

declare(strict_types=1);

namespace Example\Continuation;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Response\ContinuationResult;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Post('/operations')]
#[ContinuationResult(
    finalType: FinalDto::class,
    pollRequest: PollRequest::class,
    stateResolver: OperationStateResolver::class,
)]
final class StartRequest extends AbstractRequest
{
}
