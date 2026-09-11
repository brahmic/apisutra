<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\AuthScope;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\Enums\AuthScopeKey;

#[Post('/credentials')]
#[AuthScope(AuthScopeKey::System)]
final class CredentialsRequest extends AbstractRequest
{
    public function __construct(
        #[Body(nested: 'payload.auth.login')]
        public ?string $login = null,
        #[Query('api_key')]
        public ?string $apiKey = null,
        #[Body]
        public ?string $plain = null,
    ) {}
}
