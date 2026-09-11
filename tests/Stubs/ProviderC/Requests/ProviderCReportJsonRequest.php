<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\ProviderC\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Path;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/report/{uuid}/report.{format}')]
final class ProviderCReportJsonRequest extends AbstractRequest
{
    public function __construct(
        #[Path('uuid')]
        public string $uuid,
        #[Path('format')]
        public string $format,
        #[Query(name: 'token')]
        public string $token,
        #[Query(name: 'report-name')]
        public string $reportName,
        #[Query(name: 'timeout')]
        public ?string $timeout = null,
    ) {}
}
