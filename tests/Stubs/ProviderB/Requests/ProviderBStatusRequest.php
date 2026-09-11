<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\ProviderB\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Path;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\ProviderB\Dto\ProviderBAsyncResponseDto;

#[Get('/provider-b/status/{operationId}')]
#[Returns(ProviderBAsyncResponseDto::class)]
final class ProviderBStatusRequest extends AbstractRequest
{
    public function __construct(
        #[Path('operationId')]
        public string $operationId,
    ) {}
}
