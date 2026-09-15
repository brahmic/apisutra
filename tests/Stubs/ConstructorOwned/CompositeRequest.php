<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\ConstructorOwned;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Collections\RequestCollection;
use Brahmic\ApiSutra\Collections\ResultCollection;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\CompositeRequestInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\Continuation\UndeclaredRequest;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

#[Get('/composite')]
#[Returns(NodeDto::class)]
final class CompositeRequest extends AbstractRequest implements CompositeRequestInterface
{
    public function requests(): RequestCollection
    {
        return RequestCollection::make([new UndeclaredRequest()]);
    }

    public function aggregate(ResultCollection $results, PipelineContext $ctx): mixed
    {
        return $results->all()[0]->data;
    }
}
