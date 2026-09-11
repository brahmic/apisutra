<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Request\SkipCredentialsEnrichment;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Post('/credentials/skip')]
#[SkipCredentialsEnrichment]
final class SkipCredentialsRequest extends AbstractRequest
{
    public function __construct(
        #[Body]
        public ?string $plain = null,
    ) {}
}
