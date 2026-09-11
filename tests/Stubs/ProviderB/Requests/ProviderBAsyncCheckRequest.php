<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\ProviderB\Requests;

use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\ProviderB\Dto\ProviderBAsyncResponseDto;

#[Post('/provider-b/async-check')]
#[Returns(ProviderBAsyncResponseDto::class)]
final class ProviderBAsyncCheckRequest extends AbstractRequest
{
    public function __construct(
        #[Body]
        public string $subjectId,
        #[Body]
        public ?string $profile = null,
    ) {}
}
