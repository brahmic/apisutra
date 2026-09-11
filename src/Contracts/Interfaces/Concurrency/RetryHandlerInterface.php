<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Concurrency;

use Brahmic\ApiSutra\Config\RetryConfig;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

interface RetryHandlerInterface
{
    public function handle(
        PreparedRequest $request,
        PipelineContext $context,
        RetryConfig $config,
        int $attempt,
    ): ProviderResponse;
}
