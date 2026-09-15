<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Config;

use Brahmic\ApiSutra\Contracts\Interfaces\Auth\AuthLockProviderInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Cache\CacheIdentityProviderInterface;
use Brahmic\ApiSutra\Enums\Cache\CacheMode;

final readonly class CacheConfig
{
    public function __construct(
        public int $ttl = 3600,
        public string $prefix = '',
        public CacheMode $mode = CacheMode::Enabled,
        public ?CacheIdentityProviderInterface $identity = null,
        public ?AuthLockProviderInterface $locks = null,
    ) {
    }
}
