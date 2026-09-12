<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Extensions;

use Brahmic\ApiSutra\Contracts\Interfaces\Attributes\AttributeHandlerInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Response\ResponseHandlerInterface;
use Brahmic\ApiSutra\Enums\Hooks\Hook;
use Brahmic\ApiSutra\Enums\Hooks\HookPriority;

final class ExtensionContext
{
    public function __construct(
        private readonly ExtensionRegistry $registry,
        private readonly string $extensionName,
    ) {
    }

    public function registerCast(string $type, CastInterface $cast): void
    {
        $this->registry->registerCast($type, $cast);
    }

    public function registerHook(
        Hook $type,
        HookInterface $hook,
        HookPriority $priority = HookPriority::Normal,
    ): void {
        $this->registry->registerHook($type, $hook, $priority);
    }

    public function registerResponseHandler(
        string $mime,
        ResponseHandlerInterface $handler,
        bool $override = false,
    ): void {
        $this->registry->registerResponseHandler($mime, $handler, $override, $this->extensionName);
    }

    public function registerAttributeHandler(string $attributeClass, AttributeHandlerInterface $handler): void
    {
        $this->registry->registerAttributeHandler($attributeClass, $handler);
    }
}
