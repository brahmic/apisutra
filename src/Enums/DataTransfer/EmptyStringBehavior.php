<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Enums\DataTransfer;

enum EmptyStringBehavior: string
{
    case Keep = 'keep';
    case NullIfEmpty = 'null_if_empty';
    case NullIfBlank = 'null_if_blank';
}
