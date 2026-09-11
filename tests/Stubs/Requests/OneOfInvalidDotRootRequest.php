<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Request\RequestOneOf;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Request\OneOfMode;

#[Post('/contract/dot-root-invalid')]
#[RequestOneOf(
    name: 'dot_root_invalid',
    variants: [
        'alpha' => ['type.code'],
    ],
    mode: OneOfMode::ExactlyOne,
)]
final class OneOfInvalidDotRootRequest extends AbstractRequest
{
    public function __construct(
        #[Body]
        public string $type,
    ) {}
}
