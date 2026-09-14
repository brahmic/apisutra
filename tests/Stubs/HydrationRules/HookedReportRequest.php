<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\HydrationRules;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

#[Get('/hooked-report')]
#[Returns(ReportDto::class, unwrap: 'data')]
final class HookedReportRequest extends AbstractRequest
{
    protected function beforeHydrate(PipelineContext $context, array $data): array
    {
        $data['data'] = $data['envelope'];
        unset($data['envelope']);
        return $data;
    }
}
