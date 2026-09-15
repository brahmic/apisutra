<?php

declare(strict_types=1);

namespace Example\Continuation;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Path;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/operations/{token}')]
final class PollRequest extends AbstractRequest
{
    public function __construct(
        #[Path]
        public string $token,
    ) {
    }
}
