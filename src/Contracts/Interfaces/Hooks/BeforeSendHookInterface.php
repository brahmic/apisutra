<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Hooks;

use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

interface BeforeSendHookInterface extends HookInterface
{
    public function handle(PipelineContext $context): ?array;
}
