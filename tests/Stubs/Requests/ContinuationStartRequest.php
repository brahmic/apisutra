<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Attributes\Response\ContinuationResult;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\Dto\ContinuationFinalDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\ContinuationStartDto;

#[Get('/continuation/start')]
#[Returns(ContinuationStartDto::class)]
#[ContinuationResult(
    finalType: ContinuationFinalDto::class,
    pollRequest: ContinuationPollRequest::class,
    unwrap: 'data',
)]
final class ContinuationStartRequest extends AbstractRequest
{
    public function __construct(
        #[Query]
        public string $id,
    ) {}
}
