<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Hooks;

use Brahmic\ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final class CaptureContextHook implements HookInterface
{
    public static ?PipelineContext $context = null;

    public static function reset(): void
    {
        self::$context = null;
    }

    public function handle(PipelineContext $context): ?array
    {
        self::$context = $context;
        return null;
    }
}
