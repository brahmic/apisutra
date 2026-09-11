<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\ProviderB\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Path;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\ProviderB\Dto\ProviderBEventsResponseDto;

#[Get('/provider-b/events/{operationId}')]
#[Returns(ProviderBEventsResponseDto::class)]
final class ProviderBEventsRequest extends AbstractRequest
{
    public function __construct(
        #[Path('operationId')]
        public string $operationId,
    ) {}
}
