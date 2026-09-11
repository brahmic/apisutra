<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Attributes;

use Brahmic\ApiSutra\Attributes\AttributeContext;

interface AttributeContextHandlerInterface
{
    /**
     * Обработать атрибут в контексте стадии
     */
    public function handle(AttributeContext $context): mixed;
}
