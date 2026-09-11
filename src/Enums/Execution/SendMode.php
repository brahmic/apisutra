<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Enums\Execution;

/**
 * Режим отправки запроса.
 */
enum SendMode: string
{
    case Sync = 'sync';
    case Async = 'async';
}
