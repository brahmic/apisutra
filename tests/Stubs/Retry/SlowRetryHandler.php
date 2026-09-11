<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Retry;

use Brahmic\ApiSutra\Config\RetryConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Concurrency\RetryHandlerInterface;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;

final class SlowRetryHandler implements RetryHandlerInterface
{
    public int $calls = 0;

    public function __construct(
        private int $sleepUs,
    ) {}

    public function handle(
        PreparedRequest $request,
        PipelineContext $context,
        RetryConfig $config,
        int $attempt,
    ): ProviderResponse {
        $this->calls++;
        usleep($this->sleepUs);

        return MockResponse::serverError()->toProviderResponse($request);
    }
}
