<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Enums\Execution;

enum FailStrategy: string
{
    case FailAll = 'fail_all';
    case Partial = 'partial';
    case IgnoreErrors = 'ignore';
}
