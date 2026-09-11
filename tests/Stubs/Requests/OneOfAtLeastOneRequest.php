<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Request\RequestOneOf;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Request\OneOfMode;

#[Post('/contract/at-least-one')]
#[RequestOneOf(
    name: 'contact_payload',
    variants: [
        'email' => ['email'],
        'phone' => ['phone'],
    ],
    mode: OneOfMode::AtLeastOne,
)]
final class OneOfAtLeastOneRequest extends AbstractRequest
{
    public function __construct(
        #[Body]
        public ?string $email = null,
        #[Body]
        public ?string $phone = null,
    ) {}
}
