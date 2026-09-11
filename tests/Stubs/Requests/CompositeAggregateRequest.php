<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Collections\RequestCollection;
use Brahmic\ApiSutra\Collections\ResultCollection;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\CompositeRequestInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

#[Get('/composite')]
final class CompositeAggregateRequest extends AbstractRequest implements CompositeRequestInterface
{
    public function requests(): RequestCollection
    {
        return RequestCollection::make([
            new SimpleGetRequest('first'),
            new SimpleGetRequest('second'),
        ]);
    }

    public function aggregate(ResultCollection $results, PipelineContext $ctx): mixed
    {
        $names = [];
        foreach ($results->all() as $result) {
            $data = $result->data;
            if ($data instanceof SimpleResponseDto) {
                $names[] = $data->name;
            }
        }

        return $names;
    }
}
