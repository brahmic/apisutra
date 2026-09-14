<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Hydration;

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Extensions\ExtensionInterface;
use Brahmic\ApiSutra\Extensions\ExtensionContext;
use Brahmic\ApiSutra\Tests\Stubs\Casts\UppercaseCast;

final readonly class UppercaseExtension implements ExtensionInterface
{
    public function getName(): string
    {
        return 'hydration-cast-contract';
    }

    public function register(ExtensionContext $context): void
    {
        $context->registerCast('string', new UppercaseCast());
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
