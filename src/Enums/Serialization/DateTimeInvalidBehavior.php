<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Enums\Serialization;

enum DateTimeInvalidBehavior: string
{
    case Throw = 'throw';
    case Null = 'null';
}
