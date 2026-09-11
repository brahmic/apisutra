<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\VO\Cache;

use Brahmic\ApiSutra\Enums\Cache\CacheMode;

readonly class CacheOverride
{
    public function __construct(
        public ?CacheMode $mode = null,
        public ?int $ttl = null,
    ) {}

    public static function empty(): self
    {
        return new self();
    }
}
