<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Support;

use Brahmic\ApiSutra\Request\RequestSpecResolver;
use Brahmic\ApiSutra\Support\NullContainerProvider;
use Brahmic\ApiSutra\Support\ContainerProviderRegistry;
use Brahmic\ApiSutra\Testing\MockClient;

trait TestTrait
{
    use AssertHelpers;

    protected function resetApiSutraState(): void
    {
        MockClient::destroyGlobal();
        ContainerProviderRegistry::set(new NullContainerProvider());
        RequestSpecResolver::clearCache();
    }
}
