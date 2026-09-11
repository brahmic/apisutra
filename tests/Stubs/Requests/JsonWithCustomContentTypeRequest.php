<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Request\Header;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Post('/custom-ct')]
final class JsonWithCustomContentTypeRequest extends AbstractRequest
{
    public function __construct(
        #[Body]
        public string $data = '{}',
        #[Header('Content-Type')]
        public string $contentType = 'application/xml',
    ) {}
}
