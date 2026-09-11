<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\Dto\OutputUserDto;

#[Post('/dto-body-merge')]
final class DtoBodyMergeRequest extends AbstractRequest
{
    public function __construct(
        #[Body(nested: 'payload.data')]
        public OutputUserDto $payload,
        #[Body(nested: 'payload.meta.request_id')]
        public string $requestId,
    ) {}
}
