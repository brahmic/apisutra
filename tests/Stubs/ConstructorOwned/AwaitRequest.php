<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\ConstructorOwned;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\ContinuationResult;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\ContinuationPollRequest;

#[Get('/await')]
#[ContinuationResult(NodeDto::class, pollRequest: ContinuationPollRequest::class, unwrap: 'data')]
final class AwaitRequest extends AbstractRequest
{
}
