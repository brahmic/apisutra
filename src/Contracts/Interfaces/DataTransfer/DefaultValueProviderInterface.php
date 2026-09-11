<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer;

use Brahmic\ApiSutra\Enums\DataTransfer\ValueState;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

interface DefaultValueProviderInterface
{
    /**
     * Исходные данные DTO после unwrap/computed.
     *
     * @param array<string, mixed> $source
     */
    public function resolve(
        mixed $value,
        ValueState $state,
        array $source,
        ?PipelineContext $context,
    ): mixed;
}
