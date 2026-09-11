<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Enums\Result;

enum ResultStatus: string
{
    case SUCCESS = 'success';
    case PARTIAL = 'partial';
    case FAILED = 'failed';
}
