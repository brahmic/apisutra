<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Response;

use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

interface ResponseHandlerInterface
{
    /**
     * Может ли обработать данный response
     */
    public function supports(ProviderResponse $response): bool;

    /**
     * Обработать response
     */
    public function handle(ProviderResponse $response, PipelineContext $context): mixed;
}
