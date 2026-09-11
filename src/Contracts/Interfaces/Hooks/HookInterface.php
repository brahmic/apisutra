<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Hooks;

use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

interface HookInterface
{
    /**
     * Выполнить хук
     */
    public function handle(PipelineContext $context): ?array;
}
