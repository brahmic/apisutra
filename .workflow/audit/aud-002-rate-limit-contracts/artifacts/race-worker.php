<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Workflow\RateLimitProbe;

use Brahmic\ApiSutra\Config\RateLimitConfig;
use Brahmic\ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use Brahmic\ApiSutra\RateLimiting\RateLimiter;

require dirname(__DIR__, 4) . '/vendor/autoload.php';
require __DIR__ . '/RaceStore.php';
(new RateLimiter())->acquire(new RateLimitConfig(
    limit: 1, period: 60, behavior: RateLimitBehavior::Throw, store: new RaceStore($argv[1]),
), 'quota');
echo json_encode(['allowed' => true], JSON_THROW_ON_ERROR) . PHP_EOL;
