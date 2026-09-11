<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Behavior\Pagination;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use Brahmic\ApiSutra\Pagination\AbstractPaginatedRequest;

#[Get('/override-page')]
#[Pagination(pageParam: 'page', limitParam: 'limit')]
final class PaginatedOverrideRequest extends AbstractPaginatedRequest
{
    public static bool $withPageCalled = false;

    public static function reset(): void
    {
        self::$withPageCalled = false;
    }

    public function withPage(int $page): RequestExecutionInterface
    {
        self::$withPageCalled = true;

        return parent::withPage($page);
    }
}
