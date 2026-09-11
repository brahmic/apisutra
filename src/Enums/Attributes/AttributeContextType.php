<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Enums\Attributes;

enum AttributeContextType: string
{
    case Request = 'request';
    case Dto = 'dto';
}
