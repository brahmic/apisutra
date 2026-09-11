<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\DataTransfer;

use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

abstract readonly class AbstractResponseDto extends AbstractDto implements ResponseDtoInterface
{
    /**
     * Вычисляемые поля перед гидрацией
     * Context nullable для поддержки from() без context
     */
    #[\Override]
    public static function computed(array $data, ?PipelineContext $context = null): array
    {
        return $data;
    }
}
