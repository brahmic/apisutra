<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Pagination;

use Brahmic\ApiSutra\Config\PaginationConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Pagination\PaginationMetaResolverInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\VO\Metadata\PaginationMeta;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final class ConfigMetaResolver implements PaginationMetaResolverInterface
{
    public static bool $called = false;

    public static function reset(): void
    {
        self::$called = false;
    }

    public function resolve(
        AbstractRequest $request,
        array $response,
        array $meta,
        PaginationConfig $config,
        ?PipelineContext $context
    ): PaginationMeta
    {
        self::$called = true;

        return new PaginationMeta(
            total: 222,
            currentPage: 2,
            perPage: 20,
            hasMore: true,
            nextCursor: 'next',
        );
    }
}
