<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Continuation;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\ContinuationResult;
use Brahmic\ApiSutra\Collections\RequestCollection;
use Brahmic\ApiSutra\Collections\ResultCollection;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\CompositeRequestInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

#[Get('/continuation/composite')]
#[ContinuationResult(CountingFinalDto::class)]
final class CompositeFinalRequest extends AbstractRequest implements CompositeRequestInterface
{
    public function requests(): RequestCollection
    {
        return RequestCollection::make([new UndeclaredRequest()]);
    }

    public function aggregate(ResultCollection $results, PipelineContext $ctx): mixed
    {
        return ['phase' => 'ready', 'data' => ['value' => $results->all()[0]->data['value']]];
    }
}
