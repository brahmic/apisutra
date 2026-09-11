<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Request\RequestDiscriminator;
use Brahmic\ApiSutra\Attributes\Request\RequestOneOf;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Request\OneOfMode;

#[Post('/crypto/v3/signatures')]
#[RequestOneOf(
    name: 'signature_payload',
    variants: [
        'cloudcrypt' => ['certificateId'],
        'goskey' => ['goskeyData'],
    ],
    requiredCommon: ['type', 'contents'],
    mode: OneOfMode::ExactlyOne,
)]
#[RequestDiscriminator(
    field: 'type',
    map: [
        'KONTUR_UC' => 'cloudcrypt',
        'GOSKEY' => 'goskey',
    ],
)]
final class OneOfSignatureRequest extends AbstractRequest
{
    public function __construct(
        #[Body]
        public string $type,
        #[Body]
        public ?string $contents = null,
        #[Body]
        public ?string $certificateId = null,
        #[Body]
        public ?array $goskeyData = null,
    ) {}
}
