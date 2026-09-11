<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer;

use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

interface ResponseDtoInterface extends DtoInterface
{
    /**
     * Вычисляемые поля перед гидрацией
     * Context nullable для поддержки from() без context
     */
    public static function computed(array $data, ?PipelineContext $context = null): array;
}
