<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\Enums\TitleStatus;

#[Get('/enum-query')]
final class EnumQueryArrayRequest extends AbstractRequest
{
    /**
     * @param array<int, TitleStatus> $statuses
     */
    public function __construct(
        #[Query('status_list')]
        public array $statuses,
    ) {}
}
