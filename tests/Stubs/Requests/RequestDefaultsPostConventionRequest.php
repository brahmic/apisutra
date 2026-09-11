<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\RequestDefaults;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Request\RequestUnmappedTarget;

#[Post('/defaults')]
#[RequestDefaults(unmapped: RequestUnmappedTarget::Convention)]
final class RequestDefaultsPostConventionRequest extends AbstractRequest
{
    public function __construct(
        public string $plain,
    ) {}
}
