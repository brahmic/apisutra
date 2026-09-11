<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Enums\Pagination;

/**
 * Режимы выполнения пагинации.
 */
enum PaginationMode: string
{
    case Single = 'single';
    case All = 'all';
    case Pages = 'pages';
    case Range = 'range';
}
