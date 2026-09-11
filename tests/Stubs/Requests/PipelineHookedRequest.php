<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Hooks\AfterHydrate;
use Brahmic\ApiSutra\Attributes\Hooks\AfterResponse;
use Brahmic\ApiSutra\Attributes\Hooks\BeforeHydrate;
use Brahmic\ApiSutra\Attributes\Hooks\BeforeSend;
use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;
use Brahmic\ApiSutra\Tests\Stubs\Hooks\HookRecorder;
use Brahmic\ApiSutra\Tests\Stubs\Hooks\RecordAfterHydrateHook;
use Brahmic\ApiSutra\Tests\Stubs\Hooks\RecordAfterResponseHook;
use Brahmic\ApiSutra\Tests\Stubs\Hooks\RecordBeforeHydrateHook;
use Brahmic\ApiSutra\Tests\Stubs\Hooks\RecordBeforeSendHook;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

#[Get('/pipeline')]
#[Returns(SimpleResponseDto::class)]
#[BeforeSend(handler: RecordBeforeSendHook::class)]
#[AfterResponse(handler: RecordAfterResponseHook::class)]
#[BeforeHydrate(handler: RecordBeforeHydrateHook::class)]
#[AfterHydrate(handler: RecordAfterHydrateHook::class)]
final class PipelineHookedRequest extends AbstractRequest
{
    public function __construct(
        #[Query]
        public string $query,
    ) {}

    protected function beforeSend(PipelineContext $context): void
    {
        HookRecorder::add('beforeSend:req');
    }

    protected function afterResponse(PipelineContext $context): void
    {
        HookRecorder::add('afterResponse:req');
    }

    protected function beforeHydrate(PipelineContext $context, array $data): array
    {
        HookRecorder::add('beforeHydrate:req');
        return $data;
    }

    protected function afterHydrate(PipelineContext $context): void
    {
        HookRecorder::add('afterHydrate:req');
    }
}
