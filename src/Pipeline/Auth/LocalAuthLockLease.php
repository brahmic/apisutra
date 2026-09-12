<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pipeline\Auth;

use Brahmic\ApiSutra\Contracts\Interfaces\Auth\AuthLockLeaseInterface;

final readonly class LocalAuthLockLease implements AuthLockLeaseInterface
{
    public function __construct(
        private LocalAuthLockProvider $provider,
        private string $key,
        private string $owner,
    ) {
    }

    public function release(): bool
    {
        return $this->provider->release($this->key, $this->owner);
    }
}
