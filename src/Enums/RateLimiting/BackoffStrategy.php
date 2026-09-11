<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Enums\RateLimiting;

enum BackoffStrategy: string
{
    case Constant = 'constant';
    case Linear = 'linear';
    case Exponential = 'exponential';
}
