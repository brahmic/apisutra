<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Request\RequestOptions;
use Override;

#[Get('/runtime-scoped')]
final class RuntimeScopedRequest extends AbstractRequest
{
    #[Override]
    public function getOptions(): RequestOptions
    {
        return RequestOptions::empty()->withAuthScope('secondary');
    }

    #[Override]
    public function getAuthScopeOverride(): ?string
    {
        return 'secondary';
    }
}
