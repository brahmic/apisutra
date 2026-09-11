<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Enums\Http;

enum QueryArrayFormat: string
{
    case Brackets = 'brackets';
    case Indices = 'indices';
    case Comma = 'comma';
    case Repeat = 'repeat';
}
