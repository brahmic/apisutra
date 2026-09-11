<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Request\RequestDiscriminator;
use Brahmic\ApiSutra\Attributes\Request\RequestOneOf;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Request\OneOfMode;

#[Post('/crypto/v3/signatures/nested')]
#[RequestOneOf(
    name: 'signature_payload_nested',
    variants: [
        'cloudcrypt' => ['signature.certificateId'],
        'goskey' => ['signature.goskey.data'],
    ],
    requiredCommon: ['signature.contents', 'type'],
    mode: OneOfMode::ExactlyOne,
)]
#[RequestDiscriminator(
    field: 'type',
    map: [
        'KONTUR_UC' => 'cloudcrypt',
        'GOSKEY' => 'goskey',
    ],
)]
final class OneOfNestedSignatureRequest extends AbstractRequest
{
    /**
     * @param array<string, mixed> $signature
     */
    public function __construct(
        #[Body]
        public string $type,
        #[Body]
        public array $signature,
    ) {}
}
