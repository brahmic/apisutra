<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Behavior\Pagination;
use Brahmic\ApiSutra\Config\PaginationConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Pagination\PaginationMetaOverrideInterface;
use Brahmic\ApiSutra\Pagination\AbstractPaginatedRequest;
use Brahmic\ApiSutra\Tests\Stubs\Pagination\AttributeMetaResolver;
use Brahmic\ApiSutra\VO\Metadata\PaginationMeta;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

#[Get('/meta-method')]
#[Pagination(metaResolver: AttributeMetaResolver::class)]
final class MethodMetaOverrideRequest extends AbstractPaginatedRequest implements PaginationMetaOverrideInterface
{
    public static ?string $lastTraceId = null;

    public static function reset(): void
    {
        self::$lastTraceId = null;
    }

    public function resolvePaginationMeta(
        array $response,
        array $meta,
        PaginationConfig $config,
        ?PipelineContext $context
    ): PaginationMeta
    {
        self::$lastTraceId = $context?->traceId;

        return new PaginationMeta(
            total: 333,
            currentPage: 3,
            perPage: 30,
            hasMore: false,
            nextCursor: null,
        );
    }
}
