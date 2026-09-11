<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Enums\Cache;

enum CacheMode: string
{
    case Enabled = 'enabled';
    case Disabled = 'disabled';
    case ReadOnly = 'read_only';
    case WriteOnly = 'write_only';
}
