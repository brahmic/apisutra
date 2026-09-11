<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Extensions;

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Extensions\ExtensionInterface;
use Brahmic\ApiSutra\Extensions\ExtensionContext;

final class WildcardResponseExtension implements ExtensionInterface
{
    public function getName(): string
    {
        return 'wildcard-response-extension';
    }

    public function register(ExtensionContext $context): void
    {
        $context->registerResponseHandler('application/*', new WildcardResponseHandler(), true);
    }

    public function boot(ClientConfig $config): void
    {
    }

    public function checkDependencies(): void
    {
    }

    public function isEnabled(): bool
    {
        return true;
    }
}
