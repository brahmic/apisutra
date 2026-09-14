<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\MetadataIsolation;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\BodyRoot;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Post('/body')]
final class BodyDtoRequest extends AbstractRequest
{
    public function __construct(
        #[BodyRoot]
        public CastDto $payload = new CastDto(),
    ) {
    }
}
