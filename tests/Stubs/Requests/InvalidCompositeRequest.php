<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\DataTransfer\Validate;
use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Collections\RequestCollection;
use Brahmic\ApiSutra\Collections\ResultCollection;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\CompositeRequestInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

#[Get('/invalid-composite')]
final class InvalidCompositeRequest extends AbstractRequest implements CompositeRequestInterface
{
    #[Validate('required')]
    public ?string $token = null;

    public function requests(): RequestCollection
    {
        return RequestCollection::make([
            new SimpleGetRequest('first'),
        ]);
    }

    public function aggregate(ResultCollection $results, PipelineContext $ctx): mixed
    {
        return $results->all();
    }
}
