<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Attributes;

use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use ReflectionClass;
use ReflectionProperty;

interface AttributeHandlerInterface
{
    /**
     * Обработать атрибут
     */
    public function handle(
        object $attribute,
        ReflectionProperty|ReflectionClass $reflection,
        PipelineContext $context,
    ): void;
}
