<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pagination;

use Brahmic\ApiSutra\Config\PaginationConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Pagination\PaginationMetaResolverInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\VO\Metadata\PaginationMeta;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class DefaultPaginationMetaResolver implements PaginationMetaResolverInterface
{
    /**
     * @param array<string, mixed> $response Ответ провайдера
     * @param array<string, mixed> $meta Извлечённая meta-часть ответа
     */
    public function resolve(
        AbstractRequest $request,
        array $response,
        array $meta,
        PaginationConfig $config,
        ?PipelineContext $context
    ): PaginationMeta
    {
        $total = $meta['total'] ?? $meta['count'] ?? null;
        $currentPage = (int) ($meta['page'] ?? $meta['currentPage'] ?? $meta['current_page'] ?? 1);
        $perPage = (int) ($meta['per_page'] ?? $meta['perPage'] ?? $meta['limit'] ?? $meta['page_size'] ?? 0);
        $nextCursor = $meta['next_cursor'] ?? $meta['nextCursor'] ?? null;
        $hasMore = $meta['has_more'] ?? $meta['hasMore'] ?? null;

        if ($hasMore === null) {
            if ($nextCursor !== null) {
                $hasMore = true;
            } elseif ($total !== null && $perPage > 0) {
                $hasMore = $currentPage * $perPage < $total;
            } else {
                $hasMore = false;
            }
        }

        return new PaginationMeta(
            total: $total !== null ? (int) $total : null,
            currentPage: $currentPage,
            perPage: $perPage,
            hasMore: (bool) $hasMore,
            nextCursor: $nextCursor !== null ? (string) $nextCursor : null,
        );
    }
}
