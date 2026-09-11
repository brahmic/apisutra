<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs;

use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\Contracts\Interfaces\Cache\CacheIdentityProviderInterface;

final readonly class CacheIdentity implements CacheIdentityProviderInterface
{
    public function __construct(private ?string $identity) {}

    public function getCacheIdentity(?PreparedRequest $request = null): ?string
    {
        return $this->identity;
    }
}
