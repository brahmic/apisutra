<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\ProviderA\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\ProviderA\Dto\ProviderASyncResponseDto;

#[Get('/provider-a/basic-check')]
#[Returns(ProviderASyncResponseDto::class)]
final class ProviderABasicCheckRequest extends AbstractRequest
{
    public function __construct(
        #[Query]
        public string $subjectId,
        #[Query]
        public ?string $region = null,
    ) {}
}
