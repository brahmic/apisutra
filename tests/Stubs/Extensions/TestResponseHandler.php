<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Extensions;

use Brahmic\ApiSutra\Contracts\Interfaces\Response\ResponseHandlerInterface;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final class TestResponseHandler implements ResponseHandlerInterface
{
    public function supports(ProviderResponse $response): bool
    {
        return true;
    }

    public function handle(ProviderResponse $response, PipelineContext $context): mixed
    {
        return [
            'handled' => true,
            'status' => $response->status,
        ];
    }
}
