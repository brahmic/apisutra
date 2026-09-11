<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Enums\Http;

enum FileFormat: string
{
    case Multipart = 'multipart';
    case Binary = 'binary';
    case Base64 = 'base64';
}
