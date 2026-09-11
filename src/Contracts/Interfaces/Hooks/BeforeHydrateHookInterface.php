<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Hooks;

use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

interface BeforeHydrateHookInterface extends HookInterface
{
    /**
     * @return array Модифицированные данные
     */
    public function handle(PipelineContext $context): array;
}
