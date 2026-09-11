<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Enums\Execution;

enum RequestRole: string
{
    case Root = 'root';
    case Nested = 'nested';
    case Dependency = 'dependency';
}
