<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Enums\DataTransfer;

/**
 * Состояние значения при извлечении из ответа.
 */
enum ValueState: string
{
    case Missing = 'missing';
    case Null = 'null';
    case Present = 'present';
}
