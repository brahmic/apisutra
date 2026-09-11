<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\ProviderC\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Path;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\ProviderC\Dto\ProviderCReportResponseDto;
use Brahmic\ApiSutra\Tests\Stubs\ProviderC\Enums\ProviderCReportEvent;

#[Get('/report/{uuid}/org-judge.json')]
#[Returns(ProviderCReportResponseDto::class)]
final class ProviderCReportJudgePreviewRequest extends AbstractRequest
{
    public function __construct(
        #[Path('uuid')]
        public string $uuid,
        #[Query(name: 'token')]
        public string $token,
        #[Query(name: 'event')]
        public ProviderCReportEvent $event = ProviderCReportEvent::RolePreview,
    ) {}
}
