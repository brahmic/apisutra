<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\ProviderB\Requests;

use Brahmic\ApiSutra\Attributes\Response\Download;
use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Path;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/provider-b/download/{operationId}')]
#[Download]
final class ProviderBDownloadRequest extends AbstractRequest
{
    public function __construct(
        #[Path('operationId')]
        public string $operationId,
    ) {}
}
