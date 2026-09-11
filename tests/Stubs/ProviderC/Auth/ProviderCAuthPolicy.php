<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\ProviderC\Auth;

use Brahmic\ApiSutra\Contracts\Interfaces\Auth\AuthPolicyInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Tests\Stubs\ProviderC\Requests\ProviderCSystemOrgCheckRequest;
use Brahmic\ApiSutra\Tests\Stubs\ProviderC\Requests\ProviderCSystemPeopleCheckRequest;

/**
 * Политика auth для Provider C: только системные запросы.
 */
final readonly class ProviderCAuthPolicy implements AuthPolicyInterface
{
    public function allowedRequests(): array
    {
        return [
            ProviderCSystemPeopleCheckRequest::class,
            ProviderCSystemOrgCheckRequest::class,
        ];
    }
}
