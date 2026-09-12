<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\Dto\TokenLoginResponse;

#[Post('/token')]
#[Returns(TokenLoginResponse::class)]
final class TokenLoginRequest extends AbstractRequest
{
    public function __construct(
        #[Body] public string $username,
        #[Body] public string $password,
    ) {
    }
}
