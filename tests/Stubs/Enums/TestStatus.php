<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Enums;

enum TestStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
