<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Hooks;

use Brahmic\ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use Brahmic\ApiSutra\Exceptions\ControlFlow\EarlyReturnException;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final class EarlyReturnHook implements HookInterface
{
    public function handle(PipelineContext $context): ?array
    {
        throw new EarlyReturnException(['ok' => true]);
    }
}
