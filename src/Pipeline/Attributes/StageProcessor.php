<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pipeline\Attributes;

use Brahmic\ApiSutra\Attributes\AttributeRegistry;
use Brahmic\ApiSutra\Enums\Pipeline\PipelineStage;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class StageProcessor
{
    public function __construct(
        private AttributeRegistry $attributes,
    ) {
    }

    public function process(
        object $target,
        PipelineContext $context,
        PipelineStage $stage,
        mixed $data = null,
    ): mixed {
        return $this->attributes->processStage($target, $context, $stage, $data);
    }
}
