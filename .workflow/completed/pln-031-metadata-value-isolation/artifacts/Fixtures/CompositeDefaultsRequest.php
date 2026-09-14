<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Workflow\Pln031\Fixtures;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Collections\RequestCollection;
use Brahmic\ApiSutra\Collections\ResultCollection;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\CompositeRequestInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

#[Get('/composite-defaults')]
#[Returns(DefaultsDto::class)]
final class CompositeDefaultsRequest extends AbstractRequest implements CompositeRequestInterface
{
    public function requests(): RequestCollection
    {
        return RequestCollection::make([new DefaultsRequest()]);
    }

    public function aggregate(ResultCollection $results, PipelineContext $ctx): mixed
    {
        return ['value' => 'aggregate'];
    }
}
