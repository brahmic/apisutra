<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pagination;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Pagination\PaginableInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Request\PaginationOptions;
use Brahmic\ApiSutra\Request\RequestExecution;
use Brahmic\ApiSutra\VO\Metadata\PaginationMeta;

abstract class AbstractPaginatedRequest extends AbstractRequest implements PaginableInterface
{
    public function paginate(): Paginator
    {
        return new Paginator($this, $this->getOptions(), PaginationOptions::empty());
    }

    public function withPage(int $page): RequestExecutionInterface
    {
        return new RequestExecution(
            request: $this,
            options: $this->getOptions(),
            paginationOptions: PaginationOptions::empty()->withPage($page),
        );
    }

    public function withLimit(int $limit): RequestExecutionInterface
    {
        return new RequestExecution(
            request: $this,
            options: $this->getOptions(),
            paginationOptions: PaginationOptions::empty()->withLimit($limit),
        );
    }

    public function withCursor(?string $cursor): RequestExecutionInterface
    {
        return new RequestExecution(
            request: $this,
            options: $this->getOptions(),
            paginationOptions: PaginationOptions::empty()->withCursor($cursor),
        );
    }

    public function extractMeta(array $response): PaginationMeta
    {
        return $this->paginationHelper()->extractMeta($this, $response);
    }
}
