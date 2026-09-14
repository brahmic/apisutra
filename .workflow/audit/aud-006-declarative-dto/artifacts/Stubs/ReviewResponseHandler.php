<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Contracts\Interfaces\Response\ResponseHandlerInterface;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class ReviewResponseHandler implements ResponseHandlerInterface
{
    public function __construct(private bool $decline = false)
    {
    }

    public function supports(ProviderResponse $response): bool
    {
        return true;
    }

    public function handle(ProviderResponse $response, PipelineContext $context): mixed
    {
        return $this->decline ? null : ['handled' => true];
    }
}
