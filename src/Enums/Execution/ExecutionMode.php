<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Enums\Execution;

enum ExecutionMode: string
{
    case Sequential = 'sequential';
    case Parallel = 'parallel';
}
