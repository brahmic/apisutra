<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\Attributes\Behavior\Cache;
use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Header;
use Brahmic\ApiSutra\Auth\CacheCredentialIdentity;
use Brahmic\ApiSutra\Contracts\Interfaces\Cache\CacheIdentityProviderInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/tenants')]
#[Cache(key: 'tenant-summary')]
final class TenantCacheRequest extends AbstractRequest implements CacheIdentityProviderInterface
{
    public function __construct(#[Header('X-Tenant-Id')] public ?string $tenant = 'default') {}

    public function getCacheIdentity(?PreparedRequest $request = null): ?string
    {
        return $this->tenant === null ? null : CacheCredentialIdentity::forRequest($this->tenant, $request, ['X-Tenant-Id']);
    }
}
