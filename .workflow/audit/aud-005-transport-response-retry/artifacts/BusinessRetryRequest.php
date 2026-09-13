<?php

declare(strict_types=1);

namespace ApiSutraAudit;

use Brahmic\ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Override;

/** Синтетический бизнес-код: проверяем механизм, а не API конкретного провайдера. */
final class BusinessRetryRequest extends RetryPolicyRequest
{
    #[Override]
    protected function shouldRetry(ProviderResponse $response, int $attempt): bool
    {
        return $response->json('error') === 'fixture.not.ready';
    }
}
