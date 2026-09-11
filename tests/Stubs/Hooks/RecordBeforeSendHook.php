<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Hooks;

use Brahmic\ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final class RecordBeforeSendHook implements HookInterface
{
    public function handle(PipelineContext $context): ?array
    {
        HookRecorder::add('beforeSend:attr');
        return null;
    }
}
