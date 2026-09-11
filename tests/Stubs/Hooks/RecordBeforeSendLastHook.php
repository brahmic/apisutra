<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Hooks;

use Brahmic\ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final class RecordBeforeSendLastHook implements HookInterface
{
    public function handle(PipelineContext $context): ?array
    {
        HookRecorder::add('before-send-last');
        return null;
    }
}
