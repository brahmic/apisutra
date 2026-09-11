<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Extensions\Archive;

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Extensions\ExtensionInterface;
use Brahmic\ApiSutra\Extensions\Archive\Handlers\ArchiveResponseHandler;
use Brahmic\ApiSutra\Extensions\ExtensionContext;

final class ArchiveExtension implements ExtensionInterface
{
    private bool $enabled = true;

    #[\Override]
    public function getName(): string
    {
        return 'archive';
    }

    #[\Override]
    public function register(ExtensionContext $context): void
    {
        $context->registerResponseHandler('application/zip', new ArchiveResponseHandler());
        $context->registerResponseHandler('application/x-tar', new ArchiveResponseHandler());
        $context->registerResponseHandler('application/gzip', new ArchiveResponseHandler());
    }

    #[\Override]
    public function boot(ClientConfig $config): void
    {
    }

    #[\Override]
    public function checkDependencies(): void
    {
        if (!extension_loaded('zip') && !extension_loaded('phar')) {
            $this->enabled = false;
        }
    }

    #[\Override]
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
