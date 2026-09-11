<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Hooks\BeforeSend;
use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Hooks\HookPriority;
use Brahmic\ApiSutra\Tests\Stubs\Hooks\HookRecorder;
use Brahmic\ApiSutra\Tests\Stubs\Hooks\RecordBeforeSendFirstHook;
use Brahmic\ApiSutra\Tests\Stubs\Hooks\RecordBeforeSendLastHook;
use Brahmic\ApiSutra\Tests\Stubs\Hooks\RecordBeforeSendNormalHook;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

#[Get('/hooked')]
#[BeforeSend(RecordBeforeSendFirstHook::class, priority: HookPriority::First)]
#[BeforeSend(RecordBeforeSendNormalHook::class, priority: HookPriority::Normal)]
#[BeforeSend(RecordBeforeSendLastHook::class, priority: HookPriority::Last)]
final class HookedRequest extends AbstractRequest
{
    public function __construct(
        #[Query]
        public string $payload,
    ) {}

    protected function beforeSend(PipelineContext $context): void
    {
        HookRecorder::add('request-before-send');
    }
}
