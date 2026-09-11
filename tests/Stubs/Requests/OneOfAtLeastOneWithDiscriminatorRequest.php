<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Request\RequestDiscriminator;
use Brahmic\ApiSutra\Attributes\Request\RequestOneOf;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Request\OneOfMode;

#[Post('/contract/at-least-one-discriminator')]
#[RequestOneOf(
    name: 'contact_payload_discriminator',
    variants: [
        'email' => ['email'],
        'phone' => ['phone'],
    ],
    mode: OneOfMode::AtLeastOne,
)]
#[RequestDiscriminator(
    field: 'type',
    map: [
        'EMAIL' => 'email',
        'PHONE' => 'phone',
    ],
)]
final class OneOfAtLeastOneWithDiscriminatorRequest extends AbstractRequest
{
    public function __construct(
        #[Body]
        public string $type,
        #[Body]
        public ?string $email = null,
        #[Body]
        public ?string $phone = null,
    ) {}
}
