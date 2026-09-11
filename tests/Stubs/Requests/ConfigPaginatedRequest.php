<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Pagination\AbstractPaginatedRequest;

#[Get('/config-paginated')]
final class ConfigPaginatedRequest extends AbstractPaginatedRequest
{
    public function __construct(
        #[Query]
        public ?int $offset = null,
        #[Query]
        public ?int $size = null,
    ) {}
}
