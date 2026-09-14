<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\HydrationRules;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\ContinuationResult;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\ContinuationPollRequest;

#[Get('/await')]
#[ContinuationResult(RecordDto::class, pollRequest: ContinuationPollRequest::class, unwrap: 'data')]
final class AwaitRequest extends AbstractRequest
{
}
