<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\Enums\NonBackedStatus;

#[Get('/enum-non-backed')]
final class NonBackedQueryRequest extends AbstractRequest
{
    /**
     * @param array<int, NonBackedStatus> $statuses
     */
    public function __construct(
        #[Query('status_list')]
        public array $statuses,
    ) {}
}
