<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Enums\Configuration;

enum Environment: string
{
    case Local = 'local';
    case Testing = 'testing';
    case Staging = 'staging';
    case Production = 'production';
}
