<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Casting;

use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

interface CastInterface
{
    /**
     * Преобразование при гидрации (API → DTO)
     * Context nullable для поддержки from() без context
     */
    public function hydrate(mixed $value, ?PipelineContext $context = null): mixed;

    /**
     * Преобразование при сериализации (DTO → API)
     * Context nullable для консистентности
     */
    public function serialize(mixed $value, ?PipelineContext $context = null): mixed;
}
